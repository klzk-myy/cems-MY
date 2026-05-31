<x-app-layout title="Profit & Loss">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Profit & Loss</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $from }} to {{ $to }}</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="date" name="from" value="{{ $from }}"
                       class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-lg">
                <input type="date" name="to" value="{{ $to }}"
                       class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-lg">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Refresh
                </button>
            </form>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5] bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-900">Revenue</h3>
            </div>
            <table class="w-full">
                <thead class="border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse ($report['revenues'] ?? [] as $revenue)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $revenue['account_code'] }} - {{ $revenue['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $revenue['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-gray-500">No revenue accounts</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                    <tr class="font-semibold">
                        <td class="px-4 py-3 text-sm text-gray-900">Total Revenue</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-green-700">{{ number_format((float) ($report['total_revenue'] ?? '0'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5] bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-900">Expenses</h3>
            </div>
            <table class="w-full">
                <thead class="border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse ($report['expenses'] ?? [] as $expense)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $expense['account_code'] }} - {{ $expense['account_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $expense['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-gray-500">No expense accounts</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                    <tr class="font-semibold">
                        <td class="px-4 py-3 text-sm text-gray-900">Total Expenses</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-red-700">{{ number_format((float) ($report['total_expenses'] ?? '0'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-900">Net {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'Profit' : 'Loss' }}</p>
                <p class="text-2xl font-semibold {{ (float) ($report['net_profit'] ?? '0') >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    RM {{ number_format(abs((float) ($report['net_profit'] ?? '0')), 2) }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
