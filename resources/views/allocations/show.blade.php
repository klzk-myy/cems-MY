<x-app-layout title="Allocation #{{ $allocation->id }}">
    <div class="space-y-6">
        <x-page-header title="Allocation #{{ $allocation->id }}" :description="$allocation->user?->username . ' • ' . $allocation->currency?->code">
            <x-slot:actions>
                <x-button href="{{ route('allocations.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Details">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-ink-muted">User</dt><dd>{{ $allocation->user?->username }}</dd></div>
                <div><dt class="text-ink-muted">Branch</dt><dd>{{ $allocation->branch?->name }}</dd></div>
                <div><dt class="text-ink-muted">Counter</dt><dd>{{ $allocation->counter?->name ?? '—' }}</dd></div>
                <div><dt class="text-ink-muted">Currency</dt><dd>{{ $allocation->currency?->code }}</dd></div>
                <div><dt class="text-ink-muted">Requested</dt><dd>{{ number_format((float) $allocation->requested_quantity, 2) }}</dd></div>
                <div><dt class="text-ink-muted">Allocated</dt><dd>{{ number_format((float) $allocation->allocated_quantity, 2) }}</dd></div>
                <div><dt class="text-ink-muted">Current Balance</dt><dd>{{ number_format((float) $allocation->current_quantity, 2) }}</dd></div>
                <div><dt class="text-ink-muted">Status</dt><dd>{{ $allocation->status->label() }}</dd></div>
                <div><dt class="text-ink-muted">Requested At</dt><dd>{{ $allocation->created_at->format('d M Y H:i') }}</dd></div>
                @if($allocation->approver)
                    <div><dt class="text-ink-muted">Approved By</dt><dd>{{ $allocation->approver->username }}</dd></div>
                @endif
                @if($allocation->rejection_reason)
                    <div><dt class="text-ink-muted">Rejection Reason</dt><dd>{{ $allocation->rejection_reason }}</dd></div>
                @endif
            </dl>
        </x-card>

        @if($allocation->isPending())
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-card title="Approve Request">
                    <form method="POST" action="{{ route('allocations.approve', $allocation->id) }}">
                        @csrf
                        <x-input
                            name="approved_quantity"
                            label="Approved Quantity"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            :value="$allocation->requested_quantity"
                            required
                        />
                        <x-input
                            name="daily_limit_myr"
                            label="Daily Limit (MYR, optional)"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <div class="mt-4">
                            <x-button type="submit" variant="success">Approve</x-button>
                        </div>
                    </form>
                </x-card>

                <x-card title="Reject Request">
                    <form method="POST" action="{{ route('allocations.reject', $allocation->id) }}">
                        @csrf
                        <x-textarea
                            name="rejection_reason"
                            label="Reason (optional)"
                        />
                        <div class="mt-4">
                            <x-button type="submit" variant="danger">Reject</x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        @endif

        @if($allocation->isApproved() || $allocation->isActive())
            <x-card title="Adjust Allocation">
                <p class="text-sm text-ink-muted mb-4">
                    Increase draws additional stock from the branch pool; decrease returns unspent
                    float to the pool. Current balance: {{ number_format((float) $allocation->current_quantity, 2) }}
                    {{ $allocation->currency?->code }}.
                </p>
                <form method="POST" action="{{ route('allocations.modify', $allocation->id) }}">
                    @csrf
                    <x-input
                        name="quantity"
                        label="Amount"
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        required
                    />
                    <div class="flex gap-3 mt-4">
                        <x-button type="submit" name="direction" value="increase" variant="success">Increase</x-button>
                        <x-button type="submit" name="direction" value="decrease" variant="warning">Decrease</x-button>
                    </div>
                </form>
            </x-card>
        @endif

        @if($allocation->isActive())
            <x-card title="Return to Pool">
                <p class="text-sm text-ink-muted mb-4">
                    Returns the remaining balance ({{ number_format((float) $allocation->current_quantity, 2) }}
                    {{ $allocation->currency?->code }}) to the branch pool.
                </p>
                <form method="POST" action="{{ route('allocations.return-to-pool', $allocation->id) }}">
                    @csrf
                    <x-button type="submit" variant="warning">Return to Pool</x-button>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>
