<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profitability Analysis - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-[#0a0a0a] text-white flex flex-col">
            @include('layouts.sidebar')
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Currency Profitability Analysis</h1>
                    <p class="text-sm text-gray-500 mt-1">P&L analysis by currency position</p>
                </div>
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                </div>
            </div>

            <!-- P&L Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total Realized P&L</div>
                    <div class="text-2xl font-semibold text-gray-900 {{ $totals['realized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totals['realized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['realized_pnl'], 2) }} MYR
                    </div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total Unrealized P&L</div>
                    <div class="text-2xl font-semibold text-gray-900 {{ $totals['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totals['unrealized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['unrealized_pnl'], 2) }} MYR
                    </div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total P&L</div>
                    <div class="text-2xl font-semibold text-gray-900 {{ $totals['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totals['total_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['total_pnl'], 2) }} MYR
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <form method="GET" action="{{ route('reports.profitability') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="flex flex-col gap-2">
                        <label for="start_date" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                            class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="end_date" class="text-xs font-medium text-gray-500 uppercase tracking-wide">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                            class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                    </div>
                    <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] transition-colors">
                        Update Report
                    </button>
                </form>
            </div>

            <!-- Currency P&L Table -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Position P&L Breakdown</h2>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Currency</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Position</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Buy Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Sell Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Realized P&L</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Unrealized P&L</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Total P&L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($positions as $position)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $position['currency'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($position['position'], 2) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($position['avg_buy_rate'], 4) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($position['avg_sell_rate'], 4) }}</td>
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
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No position data available</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($positions) > 0)
                        <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">TOTAL</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">-</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">-</td>
                                <td class="px-6 py-4 text-sm text-gray-900 text-right">-</td>
                                <td class="px-6 py-4 text-sm font-medium text-right {{ $totals['realized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $totals['realized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['realized_pnl'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-right {{ $totals['unrealized_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $totals['unrealized_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['unrealized_pnl'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-right {{ $totals['total_pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $totals['total_pnl'] >= 0 ? '+' : '' }}{{ number_format($totals['total_pnl'], 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </main>
    </div>
</body>
</html>