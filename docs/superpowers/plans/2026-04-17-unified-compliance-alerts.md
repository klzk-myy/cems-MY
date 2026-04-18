# Unified Compliance Alerts Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a unified compliance alerts dashboard merging Alert Triage and Compliance Findings into a single view with comprehensive filtering.

**Architecture:** Single `UnifiedAlertController` fetches Alert records from DB and ComplianceFinding records via internal API, normalizes both into a common structure, applies unified filters, and renders a merged paginated table.

**Tech Stack:** Laravel 10, Blade templates, Tailwind CSS, Alert model (DB), ComplianceFinding model (API via Http facade)

---

## File Map

### New Files
- `app/Http/Controllers/Compliance/UnifiedAlertController.php` - Main controller
- `resources/views/compliance/unified/index.blade.php` - Unified view

### Modified Files
- `routes/web.php` - Add `GET /compliance/unified` route
- `resources/views/layouts/base.blade.php` - Add sidebar link

---

## Task 1: Create UnifiedAlertController

**Files:**
- Create: `app/Http/Controllers/Compliance/UnifiedAlertController.php`

- [ ] **Step 1: Write the controller skeleton**

```php
<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        usort($items, fn($a, $b) => $b['date']->timestamp - $a['date']->timestamp);

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
            $query->where('priority', $priority);
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
            $query->whereHas('customer', fn($q) => $q->where('full_name', 'like', "%{$customerSearch}%"));
        }
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        $items = $alerts->map(fn($alert) => [
            'id' => 'A-' . $alert->id,
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
                'ic' => $alert->customer->ic_number,
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
                'critical' => $alerts->where('priority', 'Critical')->count(),
                'pending' => $alerts->filter(fn($a) => in_array($a->status?->value, ['Pending', 'Open']))->count(),
                'resolved_today' => $alerts->whereDate('updated_at', today())->filter(fn($a) => in_array($a->status?->value, ['Resolved']))->count(),
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

        $url = config('app.url') . $this->apiBase;
        if (! empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = Http::withToken(session('api_token'))->get($url);
        $data = $response->successful() ? $response->json()['data'] ?? [] : [];

        $findings = collect($data['data'] ?? []);

        if ($customerSearch) {
            $customerIds = Customer::where('full_name', 'like', "%{$customerSearch}%")->pluck('id');
            $findings = $findings->filter(fn($f) => $f['subject_type'] === 'Customer' && in_array($f['subject_id'], $customerIds->toArray()));
        }

        $items = $findings->map(fn($finding) => [
            'id' => 'F-' . $finding['id'],
            'source' => 'Finding',
            'priority' => $finding['severity'] ?? 'Low',
            'priority_label' => $finding['severity'] ?? 'Low',
            'type' => $finding['finding_type'] ?? 'Unknown',
            'type_label' => $this->getFindingTypeLabel($finding['finding_type'] ?? ''),
            'status' => $finding['status'] ?? 'New',
            'status_label' => $this->getFindingStatusLabel($finding['status'] ?? ''),
            'customer' => $finding['subject_type'] === 'Customer' ? [
                'id' => $finding['subject_id'],
                'name' => $finding['subject_name'] ?? 'Customer #' . $finding['subject_id'],
                'ic' => null,
            ] : null,
            'assigned_to' => null,
            'description' => Str::limit($finding['details']['summary'] ?? $finding['details']['description'] ?? '', 100),
            'date' => \Carbon\Carbon::parse($finding['generated_at'] ?? now()),
            'url' => "/compliance/findings/{$finding['id']}",
        ])->toArray();

        return [
            'items' => $items,
            'stats' => [
                'total' => count($items),
                'critical' => collect($items)->where('priority', 'Critical')->count(),
                'pending' => collect($items)->whereIn('status', ['New', 'New'])->count(),
                'resolved_today' => 0,
            ],
        ];
    }

    protected function mapUnifiedStatusToAlert(string $unifiedStatus): ?string
    {
        return match ($unifiedStatus) {
            'open' => 'Open',
            'in_review' => 'UnderReview',
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
            'resolved' => 'CaseCreated',
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
```

- [ ] **Step 2: Add missing imports at top of controller**

Add these imports after the existing use statements:
```php
use Illuminate\Support\Str;
use Carbon\Carbon;
```

- [ ] **Step 3: Verify file is syntactically correct**

Run: `php -l app/Http/Controllers/Compliance/UnifiedAlertController.php`
Expected: No syntax errors

---

## Task 2: Create Unified View

**Files:**
- Create: `resources/views/compliance/unified/index.blade.php`

- [ ] **Step 1: Write the view**

```blade.php
@extends('layouts.base')

@section('title', 'Unified Compliance Alerts')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Unified Compliance Alerts</h1>
    <p class="text-sm text-[--color-ink-muted]">Alerts and findings in one view</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/compliance/alerts" class="btn btn-ghost btn-sm">Alert Triage</a>
    <a href="/compliance/findings" class="btn btn-ghost btn-sm">Findings</a>
</div>
@endsection

@section('content')
{{-- Stats Bar --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-accent]/10 text-[--color-accent]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Items</p>
        <p class="stat-card-value">{{ number_format($stats['total'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Critical</p>
        <p class="stat-card-value">{{ number_format($stats['critical'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-warning]/10 text-[--color-warning]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Pending/Open</p>
        <p class="stat-card-value">{{ number_format($stats['pending'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Resolved Today</p>
        <p class="stat-card-value">{{ number_format($stats['resolved_today'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="/compliance/unified" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="form-label">Source</label>
                <select name="source" class="form-select">
                    <option value="">All</option>
                    <option value="alert" {{ ($request->get('source') ?? '') === 'alert' ? 'selected' : '' }}>Alerts Only</option>
                    <option value="finding" {{ ($request->get('source') ?? '') === 'finding' ? 'selected' : '' }}>Findings Only</option>
                </select>
            </div>
            <div>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All</option>
                    <option value="Critical" {{ ($request->get('priority') ?? '') === 'Critical' ? 'selected' : '' }}>Critical</option>
                    <option value="High" {{ ($request->get('priority') ?? '') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Medium" {{ ($request->get('priority') ?? '') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="Low" {{ ($request->get('priority') ?? '') === 'Low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="open" {{ ($request->get('status') ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in_review" {{ ($request->get('status') ?? '') === 'in_review' ? 'selected' : '' }}>In Review</option>
                    <option value="resolved" {{ ($request->get('status') ?? '') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="dismissed" {{ ($request->get('status') ?? '') === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
                </select>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <optgroup label="Alert Types">
                        <option value="LargeAmount" {{ ($request->get('type') ?? '') === 'LargeAmount' ? 'selected' : '' }}>Large Amount</option>
                        <option value="SanctionsHit" {{ ($request->get('type') ?? '') === 'SanctionsHit' ? 'selected' : '' }}>Sanctions Hit</option>
                        <option value="Velocity" {{ ($request->get('type') ?? '') === 'Velocity' ? 'selected' : '' }}>Velocity</option>
                        <option value="Structuring" {{ ($request->get('type') ?? '') === 'Structuring' ? 'selected' : '' }}>Structuring</option>
                        <option value="EddRequired" {{ ($request->get('type') ?? '') === 'EddRequired' ? 'selected' : '' }}>EDD Required</option>
                        <option value="PepStatus" {{ ($request->get('type') ?? '') === 'PepStatus' ? 'selected' : '' }}>PEP Status</option>
                        <option value="SanctionMatch" {{ ($request->get('type') ?? '') === 'SanctionMatch' ? 'selected' : '' }}>Sanction Match</option>
                        <option value="HighRiskCustomer" {{ ($request->get('type') ?? '') === 'HighRiskCustomer' ? 'selected' : '' }}>High Risk Customer</option>
                        <option value="UnusualPattern" {{ ($request->get('type') ?? '') === 'UnusualPattern' ? 'selected' : '' }}>Unusual Pattern</option>
                        <option value="HighRiskCountry" {{ ($request->get('type') ?? '') === 'HighRiskCountry' ? 'selected' : '' }}>High Risk Country</option>
                        <option value="RoundAmount" {{ ($request->get('type') ?? '') === 'RoundAmount' ? 'selected' : '' }}>Round Amount</option>
                        <option value="CounterfeitCurrency" {{ ($request->get('type') ?? '') === 'CounterfeitCurrency' ? 'selected' : '' }}>Counterfeit Currency</option>
                    </optgroup>
                    <optgroup label="Finding Types">
                        <option value="Velocity_Exceeded" {{ ($request->get('type') ?? '') === 'Velocity_Exceeded' ? 'selected' : '' }}>Velocity Exceeded</option>
                        <option value="Structuring_Pattern" {{ ($request->get('type') ?? '') === 'Structuring_Pattern' ? 'selected' : '' }}>Structuring Pattern</option>
                        <option value="Sanction_Match" {{ ($request->get('type') ?? '') === 'Sanction_Match' ? 'selected' : '' }}>Sanction Match</option>
                        <option value="STR_Deadline" {{ ($request->get('type') ?? '') === 'STR_Deadline' ? 'selected' : '' }}>STR Deadline</option>
                        <option value="Counterfeit_Alert" {{ ($request->get('type') ?? '') === 'Counterfeit_Alert' ? 'selected' : '' }}>Counterfeit Alert</option>
                        <option value="Location_Anomaly" {{ ($request->get('type') ?? '') === 'Location_Anomaly' ? 'selected' : '' }}>Location Anomaly</option>
                        <option value="Currency_Flow_Anomaly" {{ ($request->get('type') ?? '') === 'Currency_Flow_Anomaly' ? 'selected' : '' }}>Currency Flow Anomaly</option>
                        <option value="Risk_Score_Change" {{ ($request->get('type') ?? '') === 'Risk_Score_Change' ? 'selected' : '' }}>Risk Score Change</option>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="form-label">Customer</label>
                <input type="text" name="customer" value="{{ $request->get('customer') ?? '' }}" class="form-input" placeholder="Search customer name...">
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" value="{{ $request->get('from_date') ?? '' }}" class="form-input">
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" value="{{ $request->get('to_date') ?? '' }}" class="form-input">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="/compliance/unified" class="btn btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Unified Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items ?? [] as $item)
                <tr>
                    <td>
                        @if($item['source'] === 'Alert')
                            <span class="badge badge-info">Alert</span>
                        @else
                            <span class="badge" style="background: #7c3aed; color: white;">Finding</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $priorityClass = match($item['priority'] ?? 'Low') {
                                'Critical' => 'badge-danger',
                                'High' => 'badge-warning',
                                'Medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $item['priority_label'] ?? 'Low' }}</span>
                    </td>
                    <td>
                        <span class="text-sm">{{ $item['type_label'] ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($item['customer'])
                            <a href="/customers/{{ $item['customer']['id'] }}" class="text-[--color-accent] hover:underline">
                                {{ $item['customer']['name'] }}
                            </a>
                        @else
                            <span class="text-[--color-ink-muted]">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusClass = match($item['status'] ?? '') {
                                'Open', 'New' => 'badge-info',
                                'UnderReview', 'Reviewed', 'InProgress' => 'badge-warning',
                                'Resolved', 'CaseCreated' => 'badge-success',
                                'Dismissed', 'Rejected' => 'badge-default',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $item['status_label'] ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($item['assigned_to'])
                            <span class="text-sm">{{ $item['assigned_to'] }}</span>
                        @else
                            <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $item['date']->format('d M Y') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ $item['url'] }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No items found</p>
                            <p class="empty-state-description">Try adjusting your filters or check back later</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(($pagination['last_page'] ?? 1) > 1)
        <div class="card-footer">
            <p class="text-sm text-[--color-ink-muted]">
                Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                ({{ $pagination['total'] }} total items)
            </p>
            <div class="flex gap-2">
                @if($pagination['current_page'] > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" class="btn btn-ghost btn-sm">Previous</a>
                @endif
                @if($pagination['current_page'] < $pagination['last_page'])
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" class="btn btn-ghost btn-sm">Next</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
```

Note: Pagination builds URLs manually using `request()->fullUrlWithQuery()` since `$items` is a plain array, not a Laravel collection. Page parameter is handled via query string.

---

## Task 3: Add Route

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add the route**

Find the compliance routes section in `routes/web.php` around line 254 where other compliance routes are defined, and add:

```php
Route::middleware('role:compliance')->prefix('compliance')->name('compliance.')->group(function () {
    // ... existing routes ...

    Route::get('/unified', [App\Http\Controllers\Compliance\UnifiedAlertController::class, 'index'])
        ->name('unified.index');
});
```

Place it near the existing `/compliance/alerts` route for logical grouping.

---

## Task 4: Add Sidebar Link

**Files:**
- Modify: `resources/views/layouts/base.blade.php`

- [ ] **Step 1: Add sidebar link under Compliance section**

Find the section in `base.blade.php` where other compliance sidebar links exist (around where you added CTOS, Screening, Findings links), and add:

```blade
<li>
    <a href="/compliance/unified" class="flex items-center gap-3 px-4 py-2.5 text-sm rounded-lg {{ request()->is('compliance/unified*') ? 'bg-[--color-accent] text-white' : 'text-[--color-ink-muted] hover:bg-[--color-canvas-subtle]' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Unified Alerts
    </a>
</li>
```

---

## Task 5: Test the Implementation

- [ ] **Step 1: Clear cache and verify route exists**

Run: `php artisan route:clear && php artisan route:list | grep unified`
Expected: Should show `GET /compliance/unified` route

- [ ] **Step 2: Run tests**

Run: `php artisan test --filter=Alert`
Expected: All existing alert tests still pass

- [ ] **Step 3: Manually verify**

Navigate to `/compliance/unified` and verify:
- Stats bar shows 4 cards
- Filters form is present with all filter options
- Table displays (may be empty if no data)
- Source badges show "Alert" (blue) and "Finding" (purple)

---

## Verification Checklist

After implementation, verify:
- [ ] `/compliance/unified` loads without errors
- [ ] Stats show combined counts from alerts and findings
- [ ] Source filter works (All/Alert/Findings)
- [ ] Priority filter works
- [ ] Status filter works (maps to appropriate statuses)
- [ ] Type filter shows combined alert + finding types
- [ ] Customer search works
- [ ] Date range filter works
- [ ] Table shows correct source badges
- [ ] Priority badges are color-coded correctly
- [ ] View links navigate to correct detail pages
- [ ] Sidebar link appears under Compliance section
- [ ] All existing tests pass

---

## Notes

1. **Pagination limitation**: Current implementation merges data in memory then slices. For large datasets, consider implementing proper cursor-based pagination or separate pagination per source.

2. **API dependency**: Findings are fetched via HTTP to the internal API. If the API is unavailable, only alerts will display. Consider adding error handling for API failures.

3. **Status mapping**: The unified status values (open, in_review, resolved, dismissed) map to specific Alert/Finding status enums. If enum values change, update the mapping methods.

4. **Findings API**: The Findings API uses `FindingType` enum values with underscores (e.g., `Velocity_Exceeded`) while Alert types use camelCase (e.g., `Velocity`). The filter correctly differentiates between them.
