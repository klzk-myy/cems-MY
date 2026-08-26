<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnifiedAlertIndexRequest;
use App\Models\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UnifiedAlertController extends Controller
{
    public function index(UnifiedAlertIndexRequest $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $source = $request->get('source', 'all');
        $priority = $request->get('priority');
        $status = $request->get('status');
        $type = $request->get('type');
        $customerSearch = $request->get('customer');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;

        $items = [];
        $stats = ['total' => 0, 'critical' => 0, 'pending' => 0, 'resolved_today' => 0];

        // Fetch enough rows to cover the requested page. The in-memory merge
        // below paginates the combined list, so a fixed 500-row cap would
        // silently drop data on deep pages. Cap the fetch at a sane ceiling
        // to bound memory usage.
        $fetchLimit = min(5000, max(500, $page * $perPage));

        if ($source === 'all' || $source === 'alert') {
            $alertData = $this->fetchAlerts($priority, $status, $type, $customerSearch, $fromDate, $toDate, $fetchLimit);
            $items = array_merge($items, $alertData['items']);
            $stats['total'] += $alertData['stats']['total'];
            $stats['critical'] += $alertData['stats']['critical'];
            $stats['pending'] += $alertData['stats']['pending'];
            $stats['resolved_today'] += $alertData['stats']['resolved_today'];
        }

        if ($source === 'all' || $source === 'finding') {
            $findingData = $this->fetchFindings($priority, $status, $type, $customerSearch, $fromDate, $toDate, $fetchLimit);
            $items = array_merge($items, $findingData['items']);
            $stats['total'] += $findingData['stats']['total'];
            $stats['critical'] += $findingData['stats']['critical'];
            $stats['pending'] += $findingData['stats']['pending'];
            $stats['resolved_today'] += $findingData['stats']['resolved_today'];
        }

        usort($items, fn ($a, $b) => $b['date']->timestamp - $a['date']->timestamp);

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $paginatedItems = array_slice($items, $offset, $perPage);
        $pagination = [
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ];

        // Replace full items array with the paginated slice for the view
        $items = $paginatedItems;

        return view('compliance.unified.index', compact('items', 'stats', 'pagination', 'request'));
    }

    protected function fetchAlerts(?string $priority, ?string $status, ?string $type, ?string $customerSearch, ?string $fromDate, ?string $toDate, ?int $limit = null): array
    {
        $query = Alert::with(['customer', 'assignedTo', 'flaggedTransaction']);

        if ($priority) {
            $query->where('priority', strtolower($priority));
        }
        if ($status) {
            $mappedStatus = $this->mapUnifiedStatusToAlert($status);
            if ($mappedStatus) {
                $query->where('status', $mappedStatus);
            }
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($customerSearch) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $customerSearch);
            $query->whereHas('customer', fn ($q) => $q->whereRaw('full_name like ? escape "\\"', ["%{$escaped}%"]));
        }
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Stats are computed from the full filtered set (not the fetch window)
        // so the header counts stay stable and consistent across pages. A
        // single aggregate query replaces four per-source count queries.
        $stats = $this->alertStats((clone $query));

        $alerts = $query->orderBy('created_at', 'desc')->limit($limit ?? 500)->get();

        $items = $alerts->map(fn ($alert) => [
            'id' => 'A-'.$alert->id,
            'source' => 'Alert',
            'priority' => $alert->priority->value,
            'priority_label' => $alert->priority->label(),
            'type' => $alert->type->value,
            'type_label' => $alert->type->label(),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'customer' => $alert->customer ? [
                'id' => $alert->customer->id,
                'name' => $alert->customer->full_name,
                'ic' => $alert->customer->id_number_masked ?? null,
            ] : null,
            'assigned_to' => $alert->assignedTo ? $alert->assignedTo->username : null,
            'description' => Str::limit($alert->reason, 100),
            'date' => $alert->created_at,
            'url' => "/compliance/alerts/{$alert->id}",
        ])->toArray();

        return [
            'items' => $items,
            'stats' => $stats,
        ];
    }

    protected function fetchFindings(?string $priority, ?string $status, ?string $type, ?string $customerSearch, ?string $fromDate, ?string $toDate, ?int $limit = null): array
    {
        $query = ComplianceFinding::with('subject');

        if ($priority) {
            $query->where('severity', strtolower($priority));
        }
        if ($status) {
            $mappedStatus = $this->mapUnifiedStatusToFinding($status);
            if ($mappedStatus) {
                $query->where('status', $mappedStatus);
            }
        }
        if ($type) {
            $query->where('finding_type', $type);
        }
        if ($fromDate) {
            $query->whereDate('generated_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('generated_at', '<=', $toDate);
        }

        if ($customerSearch) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $customerSearch);
            $customerIds = Customer::whereRaw('full_name like ? escape "\\"', ["%{$escaped}%"])->pluck('id');
            $query->where(function ($q) use ($customerIds) {
                $q->where('subject_type', 'Customer')
                    ->whereIn('subject_id', $customerIds);
            });
        }

        // Stats from the full filtered set so the header counts are stable.
        $stats = $this->findingStats((clone $query));

        $findings = $query->orderBy('generated_at', 'desc')->limit($limit ?? 500)->get();

        $items = $findings->map(fn ($finding) => [
            'id' => 'F-'.$finding->id,
            'source' => 'Finding',
            'priority' => $finding->severity->value,
            'priority_label' => $finding->severity->value,
            'type' => $finding->finding_type->value,
            'type_label' => $this->getFindingTypeLabel($finding->finding_type->value),
            'status' => $finding->status->value,
            'status_label' => $this->getFindingStatusLabel($finding->status->value),
            'customer' => $finding->subject_type === 'Customer' ? [
                'id' => $finding->subject_id,
                'name' => $finding->subject instanceof Customer ? $finding->subject->full_name : 'Customer #'.$finding->subject_id,
                'ic' => null,
            ] : null,
            'assigned_to' => null,
            'description' => Str::limit(isset($finding->details) ? ($finding->details['summary'] ?? $finding->details['description'] ?? '') : '', 100),
            'date' => $finding->generated_at ?? now(),
            'url' => "/compliance/findings/{$finding->id}",
        ])->toArray();

        return [
            'items' => $items,
            'stats' => $stats,
        ];
    }

    /**
     * Alert header stats from a single aggregate query.
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
                FlagStatus::Resolved->value,
                FlagStatus::Rejected->value,
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
     */
    protected function findingStats(Builder $query): array
    {
        $row = $query->selectRaw(
            'count(*) as total,'
            .'sum(case when severity = ? then 1 else 0 end) as critical,'
            .'sum(case when status not in (?, ?) then 1 else 0 end) as pending,'
            .'0 as resolved_today',
            ['Critical', 'Dismissed', 'Case_Created']
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
            'open' => 'Open',
            'in_review' => 'Under_Review',
            'resolved' => 'Resolved',
            'dismissed' => 'Rejected',
            default => null,
        };
    }

    protected function mapUnifiedStatusToFinding(string $unifiedStatus): ?string
    {
        return match ($unifiedStatus) {
            'open' => 'New',
            'in_review' => 'Reviewed',
            'resolved' => 'Case_Created',
            'dismissed' => 'Dismissed',
            default => null,
        };
    }

    protected function getFindingTypeLabel(string $type): string
    {
        return match ($type) {
            'Velocity_Exceeded' => 'Velocity Exceeded',
            'Structuring_Pattern' => 'Structuring Pattern',
            'Aggregate_Transaction' => 'Aggregate Transaction',
            'Sanction_Match' => 'Sanction Match',
            'Location_Anomaly' => 'Location Anomaly',
            'Currency_Flow_Anomaly' => 'Currency Flow Anomaly',
            'Counterfeit_Alert' => 'Counterfeit Alert',
            'Risk_Score_Change' => 'Risk Score Change',
            default => $type,
        };
    }

    protected function getFindingStatusLabel(string $status): string
    {
        return match ($status) {
            'New' => 'New',
            'Reviewed' => 'Reviewed',
            'Dismissed' => 'Dismissed',
            'Case_Created' => 'Case Created',
            default => $status,
        };
    }
}
