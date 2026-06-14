<x-app-layout title="Emergency Counter Action">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-red-600">Emergency Counter Action</h1>
            <p class="text-ink-muted text-sm mt-1">Immediate action required - supervisor approval needed</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-lg">
            <x-alert type="error" :icon="false">
                <h3 class="font-semibold text-red-800 mb-2">Action Required</h3>
                <p class="text-sm text-red-700">{{ $message ?? 'An emergency situation requires immediate attention.' }}</p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Emergency Action</label>
                    <textarea name="reason" rows="2" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required placeholder="Describe the emergency..."></textarea>
                </div>

                <x-input type="password" name="supervisor_pin" label="Supervisor PIN" required />

                <div class="flex gap-3">
                    <x-button type="submit" variant="danger">Confirm Emergency Action</x-button>
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>