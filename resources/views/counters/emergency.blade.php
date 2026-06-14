<x-app-layout title="Emergency Counter Action">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Emergency Counter Action"
            description="Immediate action required - supervisor approval needed"
        />

        <x-card class="max-w-lg p-6">
            <x-alert type="error" :icon="false">
                <h3 class="font-semibold mb-2">Action Required</h3>
                <p class="text-sm">{{ $message ?? 'An emergency situation requires immediate attention.' }}</p>
            </x-alert>

            @if(isset($counter))
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div>
                    <span class="text-ink-muted">Counter</span>
                    <p class="font-medium">{{ $counter->name }}</p>
                </div>
                <div>
                    <span class="text-ink-muted">Current Operator</span>
                    <p class="font-medium">{{ $counter->current_operator ?? 'Unknown' }}</p>
                </div>
                <div>
                    <span class="text-ink-muted">Session Started</span>
                    <p class="font-medium">{{ $counter->session_opened_at?->format('h:i A') ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-ink-muted">Current Float</span>
                    <p class="font-medium">RM {{ number_format($counter->current_float ?? 0, 2) }}</p>
                </div>
            </div>
            @endisset

            <form method="POST" action="{{ route('counters.emergency', $counter ?? 1) }}">
                @csrf
                <input type="hidden" name="action" value="{{ $action ?? 'force_close' }}">

                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-ink-muted mb-1">Reason for Emergency Action</label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="2"
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg text-ink placeholder:text-ink-muted/50 focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary"
                        required
                        placeholder="Describe the emergency..."
                    ></textarea>
                </div>

                <x-input type="password" name="supervisor_pin" label="Supervisor PIN" required />

                <div class="flex gap-3">
                    <x-button type="submit" variant="danger">Confirm Emergency Action</x-button>
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
