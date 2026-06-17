<x-app-layout title="Handover Counter">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Counter Handover"
            description="Transfer counter custody to another operator"
        />

        <x-card class="max-w-lg">
            <x-card-section>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Counter</label>
                        <p class="font-semibold text-lg text-ink">{{ $counter->name ?? 'Counter 1' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Current Operator</label>
                        <p class="font-semibold text-lg text-ink">{{ auth()->user()->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Session Started</label>
                        <p class="font-medium text-ink">{{ $session->opened_at->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Current Float</label>
                        <p class="font-medium text-ink">RM {{ number_format($session->opening_float ?? 5000, 2) }}</p>
                    </div>
                </div>
            </x-card-section>

            <x-card-section>
                <h2 class="text-lg font-semibold mb-4 text-ink">Transfer Details</h2>
                <form method="POST" action="{{ route('counters.handover', $counter ?? 1) }}">
                    @csrf

                    @php
                        $operatorOptions = collect($availableUsers ?? [])->mapWithKeys(function ($user) {
                            return [$user->id => $user->name . ' (' . $user->role . ')'];
                        })->toArray();
                    @endphp

                    <div class="mb-4">
                        <x-select
                            name="to_user_id"
                            label="Select Operator"
                            :options="$operatorOptions"
                            placeholder="-- Select Operator --"
                            required
                            inline
                        />
                    </div>

                    <div class="mb-4">
                        <x-input
                            type="password"
                            name="pin"
                            label="Your PIN to Confirm"
                            required
                            inline
                        />
                    </div>

                    <x-textarea
                        name="notes"
                        label="Notes (Optional)"
                        rows="2"
                        placeholder="Any special instructions for the next operator..."
                    >{{ old('notes') }}</x-textarea>

                    <x-alert type="warning">
                        <strong>Note:</strong> The receiving operator must acknowledge this handover to complete the transfer.
                    </x-alert>

                    <div class="flex gap-3">
                        <x-button type="submit" variant="primary">Initiate Handover</x-button>
                        <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
