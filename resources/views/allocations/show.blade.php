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
                <div><dt class="text-ink-muted">Counter</dt><dd>{{ $allocation->counter?->name }}</dd></div>
                <div><dt class="text-ink-muted">Currency</dt><dd>{{ $allocation->currency?->code }}</dd></div>
                <div><dt class="text-ink-muted">Amount</dt><dd>{{ number_format((float) $allocation->allocated_amount, 4) }}</dd></div>
                <div><dt class="text-ink-muted">Status</dt><dd>{{ $allocation->status->value }}</dd></div>
                <div><dt class="text-ink-muted">Requested</dt><dd>{{ $allocation->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
