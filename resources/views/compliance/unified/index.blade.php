<x-app-layout title="Compliance Unified Index">
    <x-page-header title="Compliance Unified Index" description="Unified alerts and findings triage" />

    @php
        $currentSource = $request->get('source', 'all');
        $priorityOptions = ['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Critical' => 'Critical'];
        $statusOptions = [
            'open' => 'Open',
            'in_review' => 'In Review',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
        ];
        $typeOptions = [
            'Velocity' => 'Velocity',
            'Structuring' => 'Structuring',
            'Large_Amount' => 'Large Amount',
            'Sanction' => 'Sanction',
            'PEP' => 'PEP',
            'Counterfeit' => 'Counterfeit',
            'Velocity_Exceeded' => 'Velocity Exceeded',
            'Structuring_Pattern' => 'Structuring Pattern',
            'Aggregate_Transaction' => 'Aggregate Transaction',
            'Sanction_Match' => 'Sanction Match',
            'Location_Anomaly' => 'Location Anomaly',
            'Currency_Flow_Anomaly' => 'Currency Flow Anomaly',
            'Counterfeit_Alert' => 'Counterfeit Alert',
            'Risk_Score_Change' => 'Risk Score Change',
        ];
    @endphp

    {{-- Header stats bar --}}
    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total Items" :value="$stats['total']" color="blue" />
        <x-stat-card label="Critical" :value="$stats['critical']" color="red" />
        <x-stat-card label="Pending/Open" :value="$stats['pending']" color="yellow" />
        <x-stat-card label="Resolved Today" :value="$stats['resolved_today']" color="green" />
    </div>

    {{-- Filter form --}}
    <form method="GET" action="{{ url('/compliance/unified') }}" class="mt-4">
        <x-filter-bar>
            <div class="flex flex-col gap-1">
                <label for="filter-source" class="text-xs font-medium text-ink-muted">Source</label>
                <select id="filter-source" name="source" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <option value="all" @selected($currentSource === 'all')>All Sources</option>
                    <option value="alert" @selected($currentSource === 'alert')>Alerts</option>
                    <option value="finding" @selected($currentSource === 'finding')>Findings</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-priority" class="text-xs font-medium text-ink-muted">Priority</label>
                <select id="filter-priority" name="priority" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <option value="">All Priorities</option>
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}" @selected($request->get('priority') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-status" class="text-xs font-medium text-ink-muted">Status</label>
                <select id="filter-status" name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($request->get('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-type" class="text-xs font-medium text-ink-muted">Type</label>
                <select id="filter-type" name="type" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <option value="">All Types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($request->get('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-customer" class="text-xs font-medium text-ink-muted">Customer</label>
                <input id="filter-customer" type="text" name="customer" value="{{ $request->get('customer') }}"
                       placeholder="Search by name" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" />
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-from" class="text-xs font-medium text-ink-muted">From Date</label>
                <input id="filter-from" type="date" name="from_date" value="{{ $request->get('from_date') }}"
                       class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" />
            </div>

            <div class="flex flex-col gap-1">
                <label for="filter-to" class="text-xs font-medium text-ink-muted">To Date</label>
                <input id="filter-to" type="date" name="to_date" value="{{ $request->get('to_date') }}"
                       class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" />
            </div>

            <div class="flex items-end gap-2">
                <x-button type="submit" variant="primary">Apply Filters</x-button>
                <a href="{{ url('/compliance/unified') }}"
                   class="rounded-lg border border-border px-3 py-2 text-sm text-ink-muted hover:bg-canvas-subtle">
                    Clear
                </a>
            </div>
        </x-filter-bar>
    </form>

    {{-- Unified items table --}}
    <x-card class="mt-4 !p-0">
        @if (empty($items))
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Source</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Priority</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Customer</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Assigned To</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8">
                            <x-empty-state title="No items found" description="No alerts or findings match the current filters." />
                        </td>
                    </tr>
                </tbody>
            </table>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Source</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Priority</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Type</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Customer</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Assigned To</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        @foreach ($items as $item)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3">
                                    <x-badge :variant="$item['source'] === 'Alert' ? 'info' : 'purple'">{{ $item['source'] }}</x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="match (strtolower($item['priority'])) {
                                        'critical' => 'error',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        default => 'gray',
                                    }">{{ $item['priority_label'] }}</x-badge>
                                </td>
                                <td class="px-4 py-3" title="{{ $item['type'] }}">{{ $item['type_label'] }}</td>
                                <td class="px-4 py-3">{{ $item['customer']['name'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $item['status_label'] }}</td>
                                <td class="px-4 py-3">{{ $item['assigned_to'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $item['date']->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $item['url'] }}" class="text-info hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($pagination['last_page'] > 1)
                <div class="flex items-center justify-between border-t border-border px-4 py-3 text-sm">
                    <span class="text-ink-muted">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} items)
                    </span>
                    <div class="flex gap-2">
                        @if ($pagination['current_page'] > 1)
                            <a href="{{ url('/compliance/unified') }}?page={{ $pagination['current_page'] - 1 }}"
                               class="rounded-lg border border-border px-3 py-1.5 hover:bg-canvas-subtle">Previous</a>
                        @endif
                        @if ($pagination['current_page'] < $pagination['last_page'])
                            <a href="{{ url('/compliance/unified') }}?page={{ $pagination['current_page'] + 1 }}"
                               class="rounded-lg border border-border px-3 py-1.5 hover:bg-canvas-subtle">Next</a>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </x-card>
</x-app-layout>