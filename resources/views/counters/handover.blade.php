<x-app-layout title="Handover Counter">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Counter Handover</h1>
            <p class="text-ink-muted text-sm mt-1">Transfer counter custody to another operator</p>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 max-w-lg">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <span class="text-ink-muted text-sm">Counter</span>
                    <p class="font-semibold text-lg">{{ $counter->name ?? 'Counter 1' }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Current Operator</span>
                    <p class="font-semibold text-lg">{{ auth()->user()->name }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Session Started</span>
                    <p class="font-medium">{{ $session->opened_at->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Current Float</span>
                    <p class="font-medium">RM {{ number_format($session->opening_float ?? 5000, 2) }}</p>
                </div>
            </div>

            <hr class="border-border my-6">

            <h2 class="text-lg font-semibold mb-4">Transfer Details</h2>
            <form method="POST" action="{{ route('counters.handover', $counter ?? 1) }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Select Operator</label>
                    <select name="to_user_id" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required>
                        <option value="">-- Select Operator --</option>
                        @foreach($availableUsers ?? [] as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Your PIN to Confirm</label>
                    <input type="password" name="pin" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" placeholder="Any special instructions for the next operator..."></textarea>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-6">
                    <p class="text-sm text-yellow-800">
                        <strong>Note:</strong> The receiving operator must acknowledge this handover to complete the transfer.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                        Initiate Handover
                    </button>
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>