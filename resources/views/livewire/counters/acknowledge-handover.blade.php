<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Acknowledge Handover - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="p-6">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Handover Details</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">From</dt>
                    <dd class="font-medium">{{ $handover->fromUser->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Supervised By</dt>
                    <dd class="font-medium">{{ $handover->supervisor->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Handover Time</dt>
                    <dd class="font-mono">{{ $handover->handover_time?->toIso8601String() ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Variance (MYR)</dt>
                    <dd class="font-mono">{{ $handover->variance_myr ?? '0.00' }}</dd>
                </div>
            </dl>
        </div>

        <form wire:submit="acknowledge">
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-4">
                    <input type="checkbox" wire:model="verified" id="verified" value="1" class="w-4 h-4" required>
                    <label for="verified" class="text-sm font-medium">I confirm the physical count has been verified and matches the expected balance</label>
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Notes (optional)</label>
                <textarea wire:model="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" placeholder="Any notes about the handover..."></textarea>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Acknowledge Handover</button>
                <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>