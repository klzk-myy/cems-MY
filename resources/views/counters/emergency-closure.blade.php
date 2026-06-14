<x-app-layout title="Emergency Counter Closure">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-red-600">Emergency Counter Closure</h1>
            <p class="text-ink-muted text-sm mt-1">Force close counter due to emergency situation</p>
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 max-w-2xl">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-red-800">Emergency Closure Notice</h3>
                        <p class="text-sm text-red-700">This action will immediately close the counter and surrender custody to management.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <span class="text-ink-muted text-sm">Counter</span>
                    <p class="font-semibold text-lg">{{ $counter->name ?? 'Counter 1' }}</p>
                </div>
                <div>
                    <span class="text-ink-muted text-sm">Current Operator</span>
                    <p class="font-semibold text-lg">{{ $currentOperator ?? 'Unknown' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('counters.emergency-close', $counter ?? 1) }}">
                @csrf

                <h3 class="text-sm font-medium text-gray-700 mb-3">Current Cash Holdings</h3>
                <div class="bg-canvas-subtle rounded-lg p-4 mb-6">
                    <table class="w-full text-sm">
                        <tr>
                            <td class="text-ink-muted py-1">MYR Cash</td>
                            <td class="text-right font-medium">RM {{ number_format($holdings['MYR'] ?? 0, 2) }}</td>
                        </tr>
                        @foreach($currencies ?? ['USD', 'SGD', 'THB'] as $currency)
                        <tr>
                            <td class="text-ink-muted py-1">{{ $currency }}</td>
                            <td class="text-right font-medium">{{ number_format($holdings[$currency] ?? 0, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="border-t border-border">
                            <td class="font-medium py-2">Total MYR Equivalent</td>
                            <td class="text-right font-semibold">RM {{ number_format($totalMYR ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Emergency Closure</label>
                    <textarea name="reason" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Manager Authorization PIN</label>
                    <input type="password" name="manager_pin" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" required>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">
                        Confirm Emergency Closure
                    </button>
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                        Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>