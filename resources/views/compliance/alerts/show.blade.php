<x-app-layout title="Alert Details - {{ $alert->id }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Alert Details"
            description="{{ $alert->id }}"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.alerts.index') }}">
                    Back to List
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Alert Details">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Alert Type</label>
                    <p class="text-sm text-ink">{{ $alert->type ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <x-badge
                        :variant="match (strtolower($alert->priority?->value ?? 'medium')) {
                            'critical', 'high' => 'danger',
                            'medium' => 'warning',
                            'low' => 'info',
                            default => 'gray',
                        }"
                    >
                        {{ $alert->priority?->value ?? 'medium' }}
                    </x-badge>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <x-badge
                        :variant="match (strtolower($alert->status?->value ?? 'open')) {
                            'resolved' => 'success',
                            'dismissed' => 'gray',
                            'open' => 'warning',
                            default => 'info',
                        }"
                    >
                        {{ $alert->status?->value ?? 'open' }}
                    </x-badge>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Customer</label>
                    <p class="text-sm text-ink">{{ $alert->customer?->full_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Created At</label>
                    <p class="text-sm text-ink">{{ $alert->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Assigned To</label>
                    <p class="text-sm text-ink">
                        @if($alert->assignedTo)
                            {{ $alert->assignedTo->username }}
                        @else
                            <span class="text-ink-muted">Unassigned</span>
                        @endif
                    </p>
                </div>
            </div>
        </x-card>

        <x-card title="Description">
            <p class="text-sm text-ink-muted">{{ $alert->reason ?? $alert->description ?? 'No description available.' }}</p>
        </x-card>

        @if($alert->flaggedTransaction || $alert->transaction)
            <x-card title="Related Transactions">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Transaction ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @php
                            $transaction = $alert->flaggedTransaction?->transaction ?? $alert->transaction;
                        @endphp
                        @if($transaction)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink">{{ $transaction->id ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at?->format('Y-m-d') ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $transaction->type?->value ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink">RM {{ number_format((float) ($transaction->amount ?? 0), 2) }}</td>
                            </tr>
                        @endif
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @endif

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('compliance.alerts.resolve', $alert) }}" class="inline">
                    @csrf
                    <input type="hidden" name="resolution" value="Resolved via alert detail page">
                    <input type="hidden" name="resolution_type" value="legitimate">
                    <x-button variant="primary" type="submit">Resolve Alert</x-button>
                </form>
                <form method="POST" action="{{ route('compliance.alerts.dismiss', $alert) }}" class="inline">
                    @csrf
                    <x-button variant="secondary" type="submit">Dismiss</x-button>
                </form>
            </div>
        </x-card>
    </div>
</x-app-layout>
