<x-app-layout title="Cases">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header
            title="Compliance Cases"
            description="Manage ongoing compliance investigations"
            class="mb-8"
        >
            <x-slot:actions>
                <x-button variant="primary">Create Case</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-select
                name="priority"
                :options="['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']"
                placeholder="All Priority"
                inline
            />
            <x-select
                name="status"
                :options="['open' => 'Open', 'in_progress' => 'In Progress', 'pending_review' => 'Pending Review', 'closed' => 'Closed']"
                placeholder="All Status"
                inline
            />
            <x-input
                name="search"
                type="text"
                placeholder="Search case ID or customer..."
                inline
            />
            <x-button variant="primary" type="submit">Search</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Case ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($cases as $case)
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->case_number }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->case_type?->label() }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->customer?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match ($case->priority?->value) {
                                        'Critical' => 'danger',
                                        'High' => 'warning',
                                        'Medium' => 'warning',
                                        'Low' => 'success',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $case->priority?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match ($case->status?->value) {
                                        'Open' => 'info',
                                        'UnderReview' => 'warning',
                                        'PendingApproval' => 'purple',
                                        'Closed' => 'success',
                                        'Escalated' => 'danger',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $case->status?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $case->created_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-button
                                    variant="ghost"
                                    size="sm"
                                    href="{{ route('compliance.cases.show', $case) }}"
                                >
                                    View
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No cases found." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
