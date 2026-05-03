<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BNM Form LMCA - Large Multiple Currency Account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f5f5] min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-[#0a0a0a]">BNM Form LMCA</h1>
            <p class="text-sm text-[#666666] mt-1">Large Multiple Currency Account Report</p>
        </div>

        {{-- Date Selector & Actions --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <form method="GET" action="{{ route('reports.lmca') }}" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="month" class="text-sm font-medium text-[#333333]">Select Month:</label>
                        <input
                            type="month"
                            id="month"
                            name="month"
                            value="{{ $month }}"
                            class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0a0a0a] focus:border-transparent"
                        />
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                    >
                        Generate Report
                    </button>
                </form>

                @if($reportGenerated)
                <div class="flex items-center gap-3">
                    <button
                        onclick="window.print()"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-[#f5f5f5]"
                    >
                        Print
                    </button>
                    <form method="POST" action="{{ route('reports.lmca.export', ['month' => $month]) }}">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-[#f5f5f5]"
                        >
                            Export
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>

        {{-- Report Content --}}
        @if($reportGenerated && !empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">

            {{-- Report Header Info --}}
            <div class="border-b border-[#e5e5e5] pb-4 mb-6">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-[#0a0a0a]">Monthly Large Cash Transaction Report</h2>
                        <p class="text-sm text-[#666666]">Reporting Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-[#999999]">Generated: {{ $reportData['generated_at'] ?? now()->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-[#666666] mb-1">Total Transactions</p>
                    <p class="text-xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-[#666666] mb-1">Total Buy Volume</p>
                    <p class="text-xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_buy_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-[#666666] mb-1">Total Sell Volume</p>
                    <p class="text-xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_sell_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="bg-gray-50 border border-[#e5e5e5] rounded-lg p-4">
                    <p class="text-xs text-[#666666] mb-1">Report Status</p>
                    <p class="text-xl font-semibold text-green-600">Generated</p>
                </div>
            </div>

            {{-- Transaction Details Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">No. of Transactions</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Buy Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Sell Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Net Volume (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['currency_breakdown'] ?? [] as $currency)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#0a0a0a] font-medium">{{ $currency['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['transaction_count']) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['buy_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['sell_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['net_volume'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-[#666666]">No transaction data available for this period</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(!empty($reportData['currency_breakdown']))
                    <tfoot>
                        <tr class="bg-[#f5f5f5] font-semibold">
                            <td class="py-3 px-4 text-[#0a0a0a]">TOTAL</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($reportData['total_transactions'] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($reportData['total_buy_volume'] ?? 0, 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($reportData['total_sell_volume'] ?? 0, 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format(($reportData['total_buy_volume'] ?? 0) - ($reportData['total_sell_volume'] ?? 0), 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{-- Footer Note --}}
            <div class="mt-6 pt-4 border-t border-[#e5e5e5]">
                <p class="text-xs text-[#999999]">
                    Note: This report includes all cash transactions equal to or exceeding RM 25,000 in MYR equivalent.
                    Report generated in compliance with Bank Negara Malaysia AML/CFT requirements.
                </p>
            </div>
        </div>
        @elseif($reportGenerated && empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">No Report Data</h3>
            <p class="text-sm text-[#666666]">No Large Multiple Currency Account transactions found for {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}.</p>
        </div>
        @else
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">Select a Month</h3>
            <p class="text-sm text-[#666666]">Choose a month above to generate the LMCA report.</p>
        </div>
        @endif

        {{-- Back Link --}}
        <div class="mt-6">
            <a href="{{ route('reports.index') }}" class="inline-flex items-center text-sm text-[#666666] hover:text-[#0a0a0a]">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Reports
            </a>
        </div>
    </div>

    <script>
        // Auto-submit form when month changes
        document.getElementById('month').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>