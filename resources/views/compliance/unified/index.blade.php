<x-app-layout title="Unified Compliance View">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Unified Compliance View"
            description="Comprehensive overview of all compliance activities"
        />

        <x-filter-bar method="GET">
            <x-select
                name="source"
                :options="['all' => 'All', 'alert' => 'Alert', 'finding' => 'Finding']"
                :selected="$request->get('source', 'all')"
                placeholder=""
                inline
            />
            <x-select
                name="priority"
                :options="['Critical' => 'Critical', 'High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low']"
                :selected="$request->get('priority')"
                placeholder="All"
                inline
            />
            <x-select
                name="status"
                :options="['open' => 'Open', 'in_review' => 'In Review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed']"
                :selected="$request->get('status')"
                placeholder="All"
                inline
            />
            <x-select
                name="type"
                :options="['Velocity_Exceeded' => 'Velocity Exceeded', 'Structuring_Pattern' => 'Structuring Pattern', 'Sanction_Match' => 'Sanction Match']"
                :selected="$request->get('type')"
                placeholder="All"
                inline
            />
            <x-input
                name="customer"
                type="text"
                placeholder="Search..."
                :value="$request->get('customer')"
                inline
            />
            <x-input
                name="from_date"
                type="date"
                label="From Date"
                :value="$request->get('from_date')"
                inline
            />
            <x-input
                name="to_date"
                type="date"
                label="To Date"
                :value="$request->get('to_date')"
                inline
            />
            <x-button variant="primary" type="submit">Apply Filters</x-button>
            <x-button variant="secondary" href="{{ route('compliance.unified.index') }}">Clear</x-button>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Items" :value="$stats['total']" />
            <x-stat-card label="Critical" :value="$stats['critical']" color="red" />
            <x-stat-card label="Pending/Open" :value="$stats['pending']" color="yellow" />
            <x-stat-card label="Resolved Today" :value="$stats['resolved_today']" color="green" />
        </x-stat-grid>

        <x-card title="Recent Activity">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm">
                                <x-badge variant="info">{{ ucfirst($item['source']) }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['id'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match ($item['priority']) {
                                        'Critical' => 'danger',
                                        'High' => 'warning',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $item['priority_label'] ?? $item['priority'] }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $item['type_label'] ?? $item['type'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['customer']['name'] ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match ($item['status']) {
                                        'Resolved', 'Case_Created' => 'success',
                                        'Dismissed', 'Rejected' => 'gray',
                                        default => 'info',
                                    }"
                                >
                                    {{ $item['status_label'] ?? $item['status'] }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['assigned_to'] ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item['date']->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ $item['url'] }}">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No items found" :colspan="9" />
                    @endforelse
                </x-slot:tbody>
            </x-table>

            @if($pagination['last_page'] > 1)
                <div class="px-6 py-4 border-t border-border flex justify-center">
                    <p class="text-sm text-ink-muted">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        (Total: {{ $pagination['total'] }} items)
                    </p>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
