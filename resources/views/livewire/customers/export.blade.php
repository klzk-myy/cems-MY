<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Export Customer Data</h3>
    </div>
    <div class="p-6">
        <p class="text-[--color-ink-muted] mb-4">Export transaction history for {{ $customer->name ?? 'N/A' }}</p>
        <div class="flex gap-3">
            <button wire:click="export" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
                Download Export
            </button>
            <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
                Back
            </a>
        </div>
    </div>
</div>