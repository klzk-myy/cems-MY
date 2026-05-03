<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Profit & Loss Statement</h1>
            <select wire:model="period" class="rounded border border-[var(--color-border)] px-3 py-2">
                @foreach($periods as $p)
                <option value="{{ $p->start_date }}_{{ $p->end_date }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Revenue</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($revenueAccounts as $account)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $account->name }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($account->balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-green-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Total Revenue</td>
                        <td class="px-4 py-2 text-right">${{ number_format($totalRevenue, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Expenses</h2>
            <table class="w-full mb-6">
                <tbody>
                    @foreach($expenseAccounts as $account)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2 pl-8">{{ $account->name }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($account->balance, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-red-50 font-bold border-t-2 border-[var(--color-border)]">
                        <td class="px-4 py-2">Total Expenses</td>
                        <td class="px-4 py-2 text-right">${{ number_format($totalExpenses, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 p-4 {{ $netIncome >= 0 ? 'bg-green-100' : 'bg-red-100' }} rounded font-bold">
                <div class="flex justify-between text-xl">
                    <span>Net Income {{ $netIncome >= 0 ? '(Profit)' : '(Loss)' }}</span>
                    <span>${{ number_format(abs($netIncome), 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>