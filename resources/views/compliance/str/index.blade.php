<x-app-layout title="STR Reports">
    <div class="space-y-6">
        <x-page-header
            title="Suspicious Transaction Reports"
            description="BNM FIED STR filings (pd-00 section 22, threshold RM 50,000)"
            class="mb-8"
        >
            <x-slot:actions>
                <a href="{{ route('compliance.str.export', request()->only('status')) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-border bg-surface hover:bg-canvas-subtle">
                    Export CSV
                </a>
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-stat-card label="Drafts" :value="$stats['drafts']" />
            <x-stat-card label="Submitted" :value="$stats['submitted']" color="yellow" />
            <x-stat-card label="Acknowledged" :value="$stats['acknowledged']" color="green" />
            <x-stat-card label="Rejected" :value="$stats['rejected']" color="red" />
        </div>

        <x-filter-bar method="GET">
            <x-select
                name="status"
                :options="[
                    'Draft' => 'Draft',
                    'Submitted' => 'Submitted',
                    'Acknowledged' => 'Acknowledged',
                    'Rejected' => 'Rejected',
                ]"
                placeholder="All Statuses"
                inline
            />
            <x-input
                name="customer"
                type="text"
                placeholder="Search customer name..."
                inline
            />
            <x-button variant="primary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table striped>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Trigger Amount (MYR)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">BNM Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Created By</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink">{{ $report->reference() }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $report->customer?->full_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink">RM {{ number_format((float) $report->trigger_amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge :variant="$report->status->color()">
                                    {{ $report->status->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $report->bnm_reference ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $report->createdBy?->username ?? 'System' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ optional($report->created_at)->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('compliance.str.show', $report) }}" class="text-info hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-ink-muted">
                                No STR reports found.
                            </td>
                        </tr>
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        {{ $reports->links() }}
    </div>
</x-app-layout>
