<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UnifiedAlertController extends Controller
{
    protected string $apiBase = '/api/v1/compliance/findings';

    public function index(Request $request)
    {
        $source = $request->get('source', 'all');
        $priority = $request->get('priority');
        $status = $request->get('status');
        $type = $request->get('type');
        $customerSearch = $request->get('customer');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $items = [];
        $stats = ['total' => 0, 'critical' => 0, 'pending' => 0, 'resolved_today' => 0];

        if ($source === 'all' || $source === 'alert') {
            $alertData = $this->fetchAlerts($source === 'all' ? null : $source, $priority, $status, $type, $customerSearch, $fromDate, $toDate);
            $items = array_merge($items, $alertData['items']);
            $stats['total'] += $alertData['stats']['total'];
            $stats['critical'] += $alertData['stats']['critical'];
            $stats['pending'] += $alertData['stats']['pending'];
            $stats['resolved_today'] += $alertData['stats']['resolved_today'];
        }

        if ($source === 'all' || $source === 'finding') {
            $findingData = $this->fetchFindings($source === 'all' ? null : $source, $priority, $status, $type, $customerSearch, $fromDate, $toDate);
            $items = array_merge($items, $findingData['items']);
            $stats['total'] += $findingData['stats']['total'];
            $stats['critical'] += $findingData['stats']['critical'];
            $stats['pending'] += $findingData['stats']['pending'];
            $stats['resolved_today'] += $findingData['stats']['resolved_today'];
        }

        usort($items, fn ($a, $b) => $b['date']->timestamp - $a['date']->timestamp);

        $perPage = 25;
        $paginatedItems = array_slice($items, 0, $perPage);
        $pagination = [
            'current_page' => 1,
            'last_page' => ceil(count($items) / $perPage),
            'per_page' => $perPage,
            'total' => count($items),
        ];

        return view('compliance.unified.index', compact('items', 'stats', 'pagination', 'request'));
    }

    protected function fetchAlerts(?string $source, ?string $priority, ?string $status, ?string $type, ?string $customerSearch, ?string $fromDate, ?string $toDate): array
    {
        $query = Alert::with(['customer', 'assignedTo']);

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
            $query->whereHas('customer', fn ($q) => $q->where('full_name', 'like', "%{$customerSearch}%"));
        }
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        $items = $alerts->map(fn ($alert) => [
            'id' => 'A-'.$alert->id,
            'source' => 'Alert',
            'priority' => $alert->priority->value ?? 'Low',
            'priority_label' => $alert->priority->label() ?? 'Low',
            'type' => $alert->type->value ?? 'Unknown',
            'type_label' => $alert->type->label() ?? 'Unknown',
            'status' => $alert->status->value ?? 'Open',
            'status_label' => $alert->status->label() ?? 'Open',
            'customer' => $alert->customer ? [
                'id' => $alert->customer->id,
                'name' => $alert->customer->full_name,
                'ic' => $alert->customer->id_number ?? null,
            ] : null,
            'assigned_to' => $alert->assignedTo ? $alert->assignedTo->username : null,
            'description' => Str::limit($alert->reason, 100),
            'date' => $alert->created_at,
            'url' => "/compliance/alerts/{$alert->id}",
        ])->toArray();

        return [
            'items' => $items,
            'stats' => [
                'total' => $alerts->count(),
                'critical' => $alerts->filter(fn ($a) => $a->priority && $a->priority->value === 'critical')->count(),
                'pending' => $alerts->filter(fn ($a) => ! in_array($a->status?->value, ['Resolved', 'Rejected', 'Dismissed']))->count(),
                'resolved_today' => $alerts->filter(fn ($a) => $a->updated_at && $a->updated_at->isToday() && in_array($a->status?->value, ['Resolved']))->count(),
            ],
        ];
    }

    protected function fetchFindings(?string $source, ?string $priority, ?string $status, ?string $type, ?string $customerSearch, ?string $fromDate, ?string $toDate): array
    {
        $params = array_filter([
            'severity' => $priority,
            'status' => $status ? $this->mapUnifiedStatusToFinding($status) : null,
            'type' => $type,
            'date_from' => $fromDate,
            'date_to' => $toDate,
        ]);

        $url = config('app.url').$this->apiBase;
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        try {
            $response = Http::withToken(session('api_token'))->get($url);
            $data = $response->successful() ? $response->json()['data'] ?? [] : [];
        } catch (\Throwable $e) {
            $data = [];
        }

        $findings = collect($data['data'] ?? []);

        if ($customerSearch) {
            $customerIds = Customer::where('full_name', 'like', "%{$customerSearch}%")->pluck('id');
            $findings = $findings->filter(fn ($f) => $f['subject_type'] === 'Customer' && in_array($f['subject_id'], $customerIds->toArray()));
        }

        $items = $findings->map(fn ($finding) => [
            'id' => 'F-'.$finding['id'],
            'source' => 'Finding',
            'priority' => $finding['severity'] ?? 'Low',
            'priority_label' => $finding['severity'] ?? 'Low',
            'type' => $finding['finding_type'] ?? 'Unknown',
            'type_label' => $this->getFindingTypeLabel($finding['finding_type'] ?? ''),
            'status' => $finding['status'] ?? 'New',
            'status_label' => $this->getFindingStatusLabel($finding['status'] ?? ''),
            'customer' => $finding['subject_type'] === 'Customer' ? [
                'id' => $finding['subject_id'],
                'name' => $finding['subject_name'] ?? 'Customer #'.$finding['subject_id'],
                'ic' => null,
            ] : null,
            'assigned_to' => null,
            'description' => Str::limit(isset($finding['details']) ? ($finding['details']['summary'] ?? $finding['details']['description'] ?? '') : '', 100),
            'date' => Carbon::parse($finding['generated_at'] ?? now()),
            'url' => "/compliance/findings/{$finding['id']}",
        ])->toArray();

        return [
            'items' => $items,
            'stats' => [
                'total' => count($items),
                'critical' => collect($items)->where('priority', 'Critical')->count(),
                'pending' => collect($items)->whereNotIn('status', ['Dismissed', 'CaseCreated'])->count(),
                'resolved_today' => 0,
            ],
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
            'STR_Deadline' => 'STR Deadline',
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
