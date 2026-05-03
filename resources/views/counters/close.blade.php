<x-app-layout title="Close Counter">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Close Counter</h1>
            <p class="text-gray-500 text-sm mt-1">Record closing balances and end your session</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-2xl">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <span class="text-gray-500 text-sm">Counter</span>
                    <p class="font-semibold text-lg">{{ $counter->name ?? 'Counter 1' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 text-sm">Operator</span>
                    <p class="font-semibold text-lg">{{ auth()->user()->name }}</p>
                </div>
                <div>
                    <span class="text-gray-500 text-sm">Session Started</span>
                    <p class="font-medium">{{ $session->opened_at->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                </div>
                <div>
                    <span class="text-gray-500 text-sm">Opening Float</span>
                    <p class="font-medium">RM {{ number_format($session->opening_float ?? 5000, 2) }}</p>
                </div>
            </div>

            <hr class="border-[#e5e5e5] my-6">

            <h2 class="text-lg font-semibold mb-4">Closing Balances</h2>
            <form method="POST" action="{{ route('counters.close.store', $counter ?? 1) }}">
                @csrf

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MYR Cash Count</label>
                        <input type="text" name="myr_cash" value="{{ old('myr_cash', $closingBalance['MYR'] ?? '') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>

                    @foreach($currencies ?? ['USD', 'SGD', 'THB'] as $currency)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $currency }} Closing Amount</label>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <span class="text-xs text-gray-500">Count</span>
                                <input type="number" step="0.01" name="currencies[{{ $currency }}][count]" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="0.00">
                            </div>
                            <div>
                                <span class="text-xs text-gray-500">Rate</span>
                                <input type="text" value="{{ $rates[$currency] ?? '0.00' }}" class="w-full px-4 py-2.5 text-sm bg-gray-50 border border-[#e5e5e5] rounded-lg" readonly>
                            </div>
                            <div>
                                <span class="text-xs text-gray-500">MYR Value</span>
                                <input type="text" class="w-full px-4 py-2.5 text-sm bg-gray-50 border border-[#e5e5e5] rounded-lg" readonly>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cash Summary</label>
                    <textarea name="summary" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="Enter cash counts summary...">{{ old('summary') }}</textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                    <input type="text" name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="Any remarks for this session...">
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Close Counter
                    </button>
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>