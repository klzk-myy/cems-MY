<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Trial Balance</h1>
            <div class="flex gap-4">
                <input type="month" wire:model="period" class="rounded border border-[var(--color-border)] px-3 py-2" />
                <button wire:click="refresh" class="px-4 py-2 border border-[var(--color-border)] rounded">Refresh</button>
                <button wire:click="export" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Export</button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Account Code</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Account Name</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Debit</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $account->code }}</td>
                        <td class="px-4 py-3">{{ $account->name }}</td>
                        <td class="px-4 py-3 text-right">{{ $account->debit_balance > 0 ? '$'.number_format($account->debit_balance, 2) : '' }}</td>
                        <td class="px-4 py-3 text-right">{{ $account->credit_balance > 0 ? '$'.number_format($account->credit_balance, 2) : '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-bold">
                    <tr>
                        <td colspan="2" class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right">${{ number_format($totalDebits, 2) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($totalCredits, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if(abs($totalDebits - $totalCredits) > 0.01)
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded text-red-800">
                Trial Balance does not balance! Difference: ${{ number_format(abs($totalDebits - $totalCredits), 2) }}
            </div>
            @endif
        </div>
    </div>
</div>