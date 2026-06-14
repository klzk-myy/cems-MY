<x-app-layout title="Currency Profitability Analysis">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Currency Profitability Analysis</h1>
                <p class="text-sm text-ink-muted mt-1">P&L analysis by currency position</p>
            </div>
            <span class="text-sm text-ink-muted">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase mb-2">Total Realized P&L</div>
                <div class="text-2xl font-semibold {{ $totals['realized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $totals['realized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['realized_pnl'], 2) }} MYR
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase mb-2">Total Unrealized P&L</div>
                <div class="text-2xl font-semibold {{ $totals['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $totals['unrealized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['unrealized_pnl'], 2) }} MYR
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="text-xs font-medium text-ink-muted uppercase mb-2">Total P&L</div>
                <div class="text-2xl font-semibold {{ $totals['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $totals['total_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['total_pnl'], 2) }} MYR
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.profitability') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="start_date" class="text-xs font-medium text-ink-muted uppercase">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label for="end_date" class="text-xs font-medium text-ink-muted uppercase">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Update Report
                </button>
            </form>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Position P&L Breakdown</h2>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Buy Rate</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Sell Rate</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Realized P&L</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Unrealized P&L</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total P&L</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse($positions as $position)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-6 py-4 text-sm font-medium text-ink">{{ $position['currency'] }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['position'], 2) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['avg_buy_rate'], 4) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['avg_sell_rate'], 4) }}</td>
                            <td class="px-6 py-4 text-sm text-right {{ $position['realized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $position['realized_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['realized_pnl'], 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right {{ $position['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $position['unrealized_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['unrealized_pnl'], 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right {{ $position['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $position['total_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['total_pnl'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-ink-muted">No position data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>