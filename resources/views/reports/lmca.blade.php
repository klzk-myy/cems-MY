<x-app-layout title="BNM Form LMCA">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">BNM Form LMCA</h1>
            @if($reportGenerated)
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                    Print
                </button>
                <form method="POST" action="{{ route('reports.lmca.export', ['month' => $month]) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                        Export
                    </button>
                </form>
            </div>
            @endif
        </div>

        {{-- Date Selector --}}
        <div class="bg-surface border border-border rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.lmca') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="month" class="text-sm font-medium text-gray-700 mb-2">Select Month</label>
                    <input type="month" id="month" name="month" value="{{ $month }}" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                    Generate Report
                </button>
            </form>
        </div>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))
        <div class="bg-surface border border-border rounded-xl p-6">
            <div class="border-b border-border pb-4 mb-6">
                <h2 class="text-lg font-semibold text-ink">Monthly Large Cash Transaction Report</h2>
                <p class="text-sm text-ink-muted">Reporting Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-canvas-subtle border border-border rounded-lg p-4">
                    <p class="text-xs text-ink-muted mb-1">Total Transactions</p>
                    <p class="text-xl font-semibold text-ink">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
                </div>
                <div class="bg-canvas-subtle border border-border rounded-lg p-4">
                    <p class="text-xs text-ink-muted mb-1">Total Buy Volume</p>
                    <p class="text-xl font-semibold text-ink">{{ number_format($reportData['total_buy_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-canvas-subtle border border-border rounded-lg p-4">
                    <p class="text-xs text-ink-muted mb-1">Total Sell Volume</p>
                    <p class="text-xl font-semibold text-ink">{{ number_format($reportData['total_sell_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-canvas-subtle border border-border rounded-lg p-4">
                    <p class="text-xs text-ink-muted mb-1">Report Status</p>
                    <p class="text-xl font-semibold text-green-600">Generated</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 font-medium text-gray-700">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">No. of Transactions</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Buy Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Sell Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Net Volume (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['currency_breakdown'] ?? [] as $currency)
                        <tr class="border-b border-border hover:bg-canvas-subtle">
                            <td class="py-3 px-4 text-ink font-medium">{{ $currency['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['transaction_count']) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['buy_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['sell_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['net_volume'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-ink-muted">No transaction data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-4 border-t border-border">
                <p class="text-xs text-ink-muted">
                    Note: This report includes all cash transactions >= RM 25,000 in MYR equivalent.
                </p>
            </div>
        </div>
        @elseif($reportGenerated && empty($reportData))
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">No Report Data</h3>
            <p class="text-sm text-ink-muted">No LMCA transactions found for {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
        </div>
        @else
        <div class="bg-surface border border-border rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-ink mb-2">Select a Month</h3>
            <p class="text-sm text-ink-muted">Choose a month above to generate the LMCA report.</p>
        </div>
        @endif
    </div>
</x-app-layout>