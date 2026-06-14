<x-app-layout title="Alert Details - {{ $alert->id }}">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Alert Details</h1>
                    <p class="mt-1 text-sm text-ink-muted">{{ $alert->id }}</p>
                </div>
                <a href="{{ route('compliance.alerts.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Alert Details Card -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Alert Type</label>
                    <p class="text-sm text-ink">{{ $alert->type ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">{{ $alert->priority ?? 'medium' }}</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">{{ $alert->status?->value ?? 'open' }}</span>
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
                            <span class="text-gray-400">Unassigned</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Description</h3>
            <p class="text-sm text-gray-600">{{ $alert->reason ?? $alert->description ?? 'No description available.' }}</p>
        </div>

        <!-- Transaction History -->
        @if($alert->flaggedTransaction || $alert->transaction)
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Related Transactions</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Transaction ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                    $transaction = $alert->flaggedTransaction?->transaction ?? $alert->transaction;
                    @endphp
                    @if($transaction)
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">{{ $transaction->id ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-ink">{{ $transaction->type?->value ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-ink">RM {{ number_format((float) ($transaction->amount ?? 0), 2) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @endif

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('compliance.alerts.resolve', $alert) }}" class="inline">
                    @csrf
                    <input type="hidden" name="resolution" value="Resolved via alert detail page">
                    <input type="hidden" name="resolution_type" value="legitimate">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Resolve Alert
                    </button>
                </form>
                <form method="POST" action="{{ route('compliance.alerts.dismiss', $alert) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                        Dismiss
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>