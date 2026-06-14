<x-app-layout title="Close Counter">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Close Counter"
            description="Record closing balances and end your session"
        />

        <x-card class="max-w-2xl">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <span class="text-ink-muted text-sm">Counter</span>
                    <p class="font-semibold text-lg">{{ $counter->name ?? 'Counter 1' }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Operator</span>
                    <p class="font-semibold text-lg">{{ auth()->user()->name }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Session Started</span>
                    <p class="font-medium">{{ $session->opened_at->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Opening Float</span>
                    <p class="font-medium">RM {{ number_format($session->opening_float ?? 5000, 2) }}</p>
                </div>
            </div>

            <hr class="border-border my-6">

            <h2 class="text-lg font-semibold mb-4">Closing Balances</h2>
            <form method="POST" action="{{ route('counters.close', $counter ?? 1) }}">
                @csrf

                <div class="space-y-4 mb-6">
                    <x-input
                        type="text"
                        name="myr_cash"
                        label="MYR Cash Count"
                        :value="old('myr_cash', $closingBalance['MYR'] ?? '')"
                        required
                        inline
                    />

                    @foreach($currencies ?? ['USD', 'SGD', 'THB'] as $currency)
                        <div>
                            <label class="block text-sm font-medium text-ink-muted mb-1">{{ $currency }} Closing Amount</label>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <span class="text-xs text-ink-muted">Count</span>
                                    <x-input
                                        type="number"
                                        step="0.01"
                                        name="currencies[{{ $currency }}][count]"
                                        placeholder="0.00"
                                        inline
                                    />
                                </div>
                                <div>
                                    <span class="text-xs text-ink-muted">Rate</span>
                                    <x-input
                                        type="text"
                                        :value="$rates[$currency] ?? '0.00'"
                                        readonly
                                        inline
                                    />
                                </div>
                                <div>
                                    <span class="text-xs text-ink-muted">MYR Value</span>
                                    <x-input
                                        type="text"
                                        readonly
                                        inline
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Cash Summary</label>
                    <textarea name="summary" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" placeholder="Enter cash counts summary...">{{ old('summary') }}</textarea>
                </div>

                <div class="mb-6">
                    <x-input
                        type="text"
                        name="notes"
                        label="Notes (Optional)"
                        placeholder="Any remarks for this session..."
                        inline
                    />
                </div>

                <div class="flex gap-3">
                    <x-button type="submit" variant="primary">Close Counter</x-button>
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
