<x-app-layout title="Close Counter">
    <div class="space-y-6">
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
                    <p class="font-semibold text-lg">{{ auth()->user()->username }}</p>
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
                    @foreach($currencies as $currency)
                        <div class="flex items-center gap-4">
                            <label class="w-20 text-sm font-medium text-ink-muted">{{ $currency->code }}</label>
                            <x-input
                                type="text"
                                name="closing_floats[{{ $currency->code }}]"
                                class="flex-1 w-full"
                                placeholder="0.00"
                                value="{{ old('closing_floats.' . $currency->code, '0.00') }}"
                                inputmode="decimal"
                                inline
                            />
                            @error('closing_floats.' . $currency->code)
                                <span class="text-xs text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mb-6">
                    <label for="supervisor_id" class="block text-sm font-medium text-ink-muted mb-1">Supervisor (required if variance exceeds the red threshold)</label>
                    <select id="supervisor_id" name="supervisor_id" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm">
                        <option value="">— None —</option>
                        @foreach($supervisors ?? [] as $supervisor)
                            <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id') === (string) $supervisor->id)>{{ $supervisor->username }}</option>
                        @endforeach
                    </select>
                    @error('supervisor_id')
                        <span class="text-xs text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <x-textarea
                    name="notes"
                    label="Notes (Optional)"
                    rows="3"
                    placeholder="Any remarks for this session..."
                >{{ old('notes') }}</x-textarea>

                <div class="flex gap-3">
                    <x-button type="submit" variant="primary">Close Counter</x-button>
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
