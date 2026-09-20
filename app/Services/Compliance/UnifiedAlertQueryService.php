<?php

namespace App\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Enums\FlagStatus;
use App\Models\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Models\User;
use App\ValueObjects\UnifiedAlertFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Merges compliance Alerts and monitor-generated ComplianceFindings into one
 * paginated, filterable feed via a SQL UNION — pagination and ordering happen
 * in the database instead of slicing a merged PHP array.
 *
 * @phpstan-type UnifiedRow object{id: int, source: string, priority: string, type: string, status: string, details: string|null, date: string|null, customer_id: int|null, customer_name: string|null, assigned_to: string|null}
 */
class UnifiedAlertQueryService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, stats: array<string, int>, pagination: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function page(UnifiedAlertFilters $filters): array
    {
        $stats = ['total' => 0, 'critical' => 0, 'pending' => 0, 'resolved_today' => 0];
        $union = null;

        if ($filters->includesAlerts()) {
            $alertsBase = $this->alertsBaseQuery($filters);
            $alertStats = $this->alertStats(clone $alertsBase);
            $stats['total'] += $alertStats['total'];
            $stats['critical'] += $alertStats['critical'];
            $stats['pending'] += $alertStats['pending'];
            $stats['resolved_today'] += $alertStats['resolved_today'];

            $union = $this->alertSelectQuery($alertsBase);
        }

        if ($filters->includesFindings()) {
            $findingsBase = $this->findingsBaseQuery($filters);
            $findingStats = $this->findingStats(clone $findingsBase);
            $stats['total'] += $findingStats['total'];
            $stats['critical'] += $findingStats['critical'];
            $stats['pending'] += $findingStats['pending'];
            $stats['resolved_today'] += $findingStats['resolved_today'];

            $findingSelect = $this->findingSelectQuery($findingsBase);
            $union = $union ? $union->unionAll($findingSelect) : $findingSelect;
        }

        if (! $union) {
            return [
                'items' => [],
                'stats' => $stats,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $filters->perPage,
                    'total' => 0,
                ],
            ];
        }

        // (source, id) is unique across the UNION, so ordering is total and
        // pages can't shuffle same-timestamp rows between requests.
        $paginator = DB::query()
            ->fromSub($union, 'unified_items')
            ->orderByDesc('date')
            ->orderBy('source')
            ->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);

        /** @var Collection<int, UnifiedRow> $rows */
        $rows = collect($paginator->items());

        // Resolve customer IDs for just this page's rows — the accessor
        // decrypts the stored value and cannot run inside the UNION query.
        $customers = Customer::whereIn('id', $rows->pluck('customer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return [
            'items' => $rows->map(fn ($row) => $this->mapUnifiedRow($row, $customers))->all(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Filtered alert query shared by the stats aggregate and the UNION select.
     *
     * @return Builder<Alert>
     */
    protected function alertsBaseQuery(UnifiedAlertFilters $filters): Builder
    {
        $alerts = (new Alert)->getTable();
        $query = Alert::query();

        if ($filters->priority) {
            $query->where("{$alerts}.priority", strtolower($filters->priority));
        }
        if ($filters->status) {
            $mappedStatus = $this->mapUnifiedStatusToAlert($filters->status);
            if ($mappedStatus) {
                $query->where("{$alerts}.status", $mappedStatus);
            }
        }
        if ($filters->type) {
            $query->where("{$alerts}.type", $filters->type);
        }
        if ($filters->customerSearch) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $filters->customerSearch);
            $query->whereHas('customer', fn ($q) => $q->whereRaw('full_name like ? escape "\\"', ["%{$escaped}%"]));
        }
        if ($filters->fromDate) {
            $query->where("{$alerts}.created_at", '>=', Carbon::parse($filters->fromDate)->startOfDay());
        }
        if ($filters->toDate) {
            $query->where("{$alerts}.created_at", '<=', Carbon::parse($filters->toDate)->endOfDay());
        }

        return $query;
    }

    /**
     * Filtered findings query shared by the stats aggregate and the UNION select.
     *
     * @return Builder<ComplianceFinding>
     */
    protected function findingsBaseQuery(UnifiedAlertFilters $filters): Builder
    {
        $findings = (new ComplianceFinding)->getTable();
        $query = ComplianceFinding::query();

        if ($filters->priority) {
            $query->where("{$findings}.severity", strtolower($filters->priority));
        }
        if ($filters->status) {
            $mappedStatus = $this->mapUnifiedStatusToFinding($filters->status);
            if ($mappedStatus) {
                $query->where("{$findings}.status", $mappedStatus);
            }
        }
        if ($filters->type) {
            $query->where("{$findings}.finding_type", $filters->type);
        }
        if ($filters->fromDate) {
            $query->where("{$findings}.generated_at", '>=', Carbon::parse($filters->fromDate)->startOfDay());
        }
        if ($filters->toDate) {
            $query->where("{$findings}.generated_at", '<=', Carbon::parse($filters->toDate)->endOfDay());
        }
        if ($filters->customerSearch) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $filters->customerSearch);
            $customerIds = Customer::whereRaw('full_name like ? escape "\\"', ["%{$escaped}%"])->pluck('id');
            $query->where(function ($q) use ($findings, $customerIds) {
                $q->where("{$findings}.subject_type", 'Customer')
                    ->whereIn("{$findings}.subject_id", $customerIds);
            });
        }

        return $query;
    }

    /**
     * Normalized alert projection for the UNION. Column order must match
     * findingSelectQuery().
     *
     * @param  Builder<Alert>  $base
     * @return Builder<Alert>
     */
    protected function alertSelectQuery(Builder $base): Builder
    {
        $alerts = (new Alert)->getTable();
        $customers = (new Customer)->getTable();

        return $base
            ->leftJoin($customers, "{$alerts}.customer_id", '=', "{$customers}.id")
            ->leftJoin((new User)->getTable().' as assignees', "{$alerts}.assigned_to", '=', 'assignees.id')
            ->select([
                "{$alerts}.id",
                DB::raw("'alert' as source"),
                "{$alerts}.priority",
                "{$alerts}.type",
                "{$alerts}.status",
                "{$alerts}.reason as details",
                "{$alerts}.created_at as date",
                "{$customers}.id as customer_id",
                "{$customers}.full_name as customer_name",
                'assignees.username as assigned_to',
            ]);
    }

    /**
     * Normalized findings projection for the UNION. Column order must match
     * alertSelectQuery().
     *
     * @param  Builder<ComplianceFinding>  $base
     * @return Builder<ComplianceFinding>
     */
    protected function findingSelectQuery(Builder $base): Builder
    {
        $findings = (new ComplianceFinding)->getTable();
        $customers = (new Customer)->getTable();

        return $base
            ->leftJoin($customers, function (JoinClause $join) use ($findings, $customers) {
                $join->on("{$findings}.subject_id", '=', "{$customers}.id")
                    ->where("{$findings}.subject_type", '=', 'Customer');
            })
            ->select([
                "{$findings}.id",
                DB::raw("'finding' as source"),
                "{$findings}.severity as priority",
                "{$findings}.finding_type as type",
                "{$findings}.status",
                "{$findings}.details",
                "{$findings}.generated_at as date",
                DB::raw("case when {$findings}.subject_type = 'Customer' then {$findings}.subject_id else null end as customer_id"),
                "{$customers}.full_name as customer_name",
                DB::raw('NULL as assigned_to'),
            ]);
    }

    /**
     * Map one UNION row to the view item shape. Raw DB rows carry string enum
     * values, so labels are resolved through the same helpers as before.
     *
     * @param  UnifiedRow  $row
     * @param  Collection<int, Customer>  $customers
     * @return array<string, mixed>
     */
    protected function mapUnifiedRow(object $row, Collection $customers): array
    {
        $isAlert = $row->source === 'alert';
        $customer = $row->customer_id ? $customers->get($row->customer_id) : null;

        $description = $row->details ?? '';
        if (! $isAlert) {
            $decoded = json_decode($description, true);
            $description = is_array($decoded) ? ($decoded['summary'] ?? $decoded['description'] ?? '') : $description;
        }

        $priority = $isAlert
            ? AlertPriority::tryFrom($row->priority)
            : null;

        return [
            'id' => ($isAlert ? 'A-' : 'F-').$row->id,
            'source' => $isAlert ? 'Alert' : 'Finding',
            'priority' => $row->priority,
            'priority_label' => $isAlert
                ? ($priority?->label() ?? $row->priority)
                : $row->priority,
            'type' => $row->type,
            'type_label' => $isAlert
                ? (ComplianceFlagType::tryFrom($row->type)?->label() ?? $row->type)
                : (FindingType::tryFrom($row->type)?->label() ?? $row->type),
            'status' => $row->status,
            'status_label' => $this->statusLabel($isAlert, (string) $row->status),
            'customer' => $row->customer_id ? [
                'id' => $row->customer_id,
                'name' => $customer->full_name ?? $row->customer_name ?? 'Customer #'.$row->customer_id,
                'ic' => $customer?->id_number,
            ] : null,
            'assigned_to' => $row->assigned_to,
            'description' => Str::limit($description, 100),
            'date' => $row->date ? Carbon::parse($row->date) : now(),
            'url' => $isAlert ? "/compliance/alerts/{$row->id}" : "/compliance/findings/{$row->id}",
        ];
    }

    /**
     * Label lookup tolerant of legacy status vocabularies. Canonical values
     * are lowercase-snake; legacy rows may be TitleCase or already
     * underscored ('Under_Review'), which Str::snake alone turns into
     * 'under__review' — collapsing underscores first fixes that.
     */
    private function statusLabel(bool $isAlert, string $status): string
    {
        $normalized = Str::snake(str_replace('_', ' ', $status));

        $enum = $isAlert
            ? FlagStatus::tryFrom($normalized)
            : FindingStatus::tryFrom($normalized);

        return $enum?->label() ?? $status;
    }

    /**
     * Alert header stats from a single aggregate query.
     *
     * @param  Builder<Alert>  $query
     * @return array{total: int, critical: int, pending: int, resolved_today: int}
     */
    protected function alertStats(Builder $query): array
    {
        $row = $query->selectRaw(
            'count(*) as total,'
            .'sum(case when priority = ? then 1 else 0 end) as critical,'
            .'sum(case when status not in (?, ?) then 1 else 0 end) as pending,'
            .'sum(case when status = ? and updated_at >= ? and updated_at <= ? then 1 else 0 end) as resolved_today',
            [
                AlertPriority::Critical->value,
                ...FlagStatus::terminalValues(),
                FlagStatus::Resolved->value,
                today()->startOfDay(),
                today()->endOfDay(),
            ]
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'critical' => (int) ($row->critical ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'resolved_today' => (int) ($row->resolved_today ?? 0),
        ];
    }

    /**
     * Finding header stats from a single aggregate query.
     *
     * @param  Builder<ComplianceFinding>  $query
     * @return array{total: int, critical: int, pending: int, resolved_today: int}
     */
    protected function findingStats(Builder $query): array
    {
        $row = $query->selectRaw(
            'count(*) as total,'
            .'sum(case when severity = ? then 1 else 0 end) as critical,'
            .'sum(case when status not in (?, ?) then 1 else 0 end) as pending,'
            .'0 as resolved_today',
            [
                FindingSeverity::Critical->value,
                FindingStatus::Dismissed->value,
                FindingStatus::CaseCreated->value,
            ]
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'critical' => (int) ($row->critical ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'resolved_today' => 0,
        ];
    }

    protected function mapUnifiedStatusToAlert(string $unifiedStatus): ?string
    {
        return match ($unifiedStatus) {
            'open' => FlagStatus::Open->value,
            'in_review' => FlagStatus::UnderReview->value,
            'resolved' => FlagStatus::Resolved->value,
            'dismissed' => FlagStatus::Rejected->value,
            default => null,
        };
    }

    protected function mapUnifiedStatusToFinding(string $unifiedStatus): ?string
    {
        return match ($unifiedStatus) {
            'open' => FindingStatus::New->value,
            'in_review' => FindingStatus::Reviewed->value,
            'resolved' => FindingStatus::CaseCreated->value,
            'dismissed' => FindingStatus::Dismissed->value,
            default => null,
        };
    }
}
