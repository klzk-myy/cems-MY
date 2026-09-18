<x-app-layout title="Cases">
    <div class="space-y-6">
        <x-page-header
            title="Compliance Cases"
            description="Manage ongoing compliance investigations"
            class="mb-8"
        >
            <x-slot:actions>
                <x-button variant="primary" href="{{ route('compliance.alerts.index') }}">Create from Alerts</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-select
                name="priority"
                :options="['Critical' => 'Critical', 'High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low']"
                placeholder="All Priority"
                inline
            />
            <x-select
                name="status"
                :options="[\App\Enums\ComplianceCaseStatus::Open->value => 'Open', \App\Enums\ComplianceCaseStatus::UnderReview->value => 'Under Review', \App\Enums\ComplianceCaseStatus::PendingApproval->value => 'Pending Approval', \App\Enums\ComplianceCaseStatus::Closed->value => 'Closed']"
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
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->customer?->full_name ?? 'N/A' }}</td>
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
                                    :variant="match ($case->status) {
                                        \App\Enums\ComplianceCaseStatus::Open => 'info',
                                        \App\Enums\ComplianceCaseStatus::UnderReview => 'warning',
                                        \App\Enums\ComplianceCaseStatus::PendingApproval => 'purple',
                                        \App\Enums\ComplianceCaseStatus::Closed => 'success',
                                        \App\Enums\ComplianceCaseStatus::Escalated => 'danger',
                                        default => 'gray',
                                    }"
                                >
                                    {{ $case->status?->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $case->assignee?->username ?? 'Unassigned' }}</td>
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
