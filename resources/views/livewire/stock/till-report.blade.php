<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('stock.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Stock</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Till Report - {{ $reportDate }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Opening Balance</label>
                    <p class="mt-1 text-2xl font-bold">${{ number_format($openingBalance, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Cash In</label>
                    <p class="mt-1 text-2xl font-bold text-green-600">${{ number_format($cashIn, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Cash Out</label>
                    <p class="mt-1 text-2xl font-bold text-red-600">${{ number_format($cashOut, 2) }}</p>
                </div>
            </div>

            <div class="border-t border-[var(--color-border)] pt-4">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Closing Balance</label>
                <p class="mt-1 text-3xl font-bold">${{ number_format($closingBalance, 2) }}</p>
            </div>

            @if($variance !== 0)
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded">
                <p class="text-red-800">Variance: ${{ number_format($variance, 2) }}</p>
            </div>
            @endif

            <div class="mt-6">
                <h3 class="text-lg font-medium text-[var(--color-ink)] mb-4">Transactions</h3>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Time</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr class="border-t border-[var(--color-border)]">
                            <td class="px-4 py-2">{{ $tx->created_at->format('H:i') }}</td>
                            <td class="px-4 py-2">{{ ucfirst($tx->type) }}</td>
                            <td class="px-4 py-2">{{ $tx->description }}</td>
                            <td class="px-4 py-2 text-right {{ $tx->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $tx->type === 'in' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-center">No transactions</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <button wire:click="export" class="px-4 py-2 border border-[var(--color-border)] rounded">Export PDF</button>
                <button wire:click="approve" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Approve Report</button>
            </div>
        </div>
    </div>
</div>