<x-app-layout title="BNM Form LMCA">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">BNM Form LMCA</h1>
            @if($reportGenerated)
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Print
                </button>
                <form method="POST" action="{{ route('reports.lmca.export', ['month' => $month]) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Export
                    </button>
                </form>
            </div>
            @endif
        </div>

        {{-- Date Selector --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.lmca') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="month" class="text-sm font-medium text-gray-700 mb-2">Select Month</label>
                    <input type="month" id="month" name="month" value="{{ $month }}" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Generate Report
                </button>
            </form>
        </div>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="border-b border-[#e5e5e5] pb-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Monthly Large Cash Transaction Report</h2>
                <p class="text-sm text-gray-500">Reporting Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Transactions</p>
                    <p class="text-xl font-semibold text-gray-900">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Buy Volume</p>
                    <p class="text-xl font-semibold text-gray-900">{{ number_format($reportData['total_buy_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Sell Volume</p>
                    <p class="text-xl font-semibold text-gray-900">{{ number_format($reportData['total_sell_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Report Status</p>
                    <p class="text-xl font-semibold text-green-600">Generated</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-gray-700">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">No. of Transactions</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Buy Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Sell Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-700">Net Volume (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['currency_breakdown'] ?? [] as $currency)
                        <tr class="border-b border-[#e5e5e5] hover:bg-gray-50">
                            <td class="py-3 px-4 text-gray-900 font-medium">{{ $currency['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['transaction_count']) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['buy_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['sell_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ number_format($currency['net_volume'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-500">No transaction data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-4 border-t border-[#e5e5e5]">
                <p class="text-xs text-gray-500">
                    Note: This report includes all cash transactions >= RM 25,000 in MYR equivalent.
                </p>
            </div>
        </div>
        @elseif($reportGenerated && empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Report Data</h3>
            <p class="text-sm text-gray-500">No LMCA transactions found for {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
        </div>
        @else
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Select a Month</h3>
            <p class="text-sm text-gray-500">Choose a month above to generate the LMCA report.</p>
        </div>
        @endif
    </div>
</x-app-layout>