<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarterly Large Value Report (LVR)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f5f5] min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-[#0a0a0a]">Quarterly Large Value Report</h1>
            <p class="text-sm text-[#666666] mt-1">QLVR - Quarterly Large Value Transaction Report</p>
        </div>

        {{-- Quarter Selector & Actions --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <form method="GET" action="{{ route('reports.quarterly-lvr') }}" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="quarter" class="text-sm font-medium text-[#333333]">Select Quarter:</label>
                        <select
                            id="quarter"
                            name="quarter"
                            class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0a0a0a] focus:border-transparent"
                        >
                            @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                                <option value="{{ $y }}-Q1" {{ $quarter === $y . '-Q1' ? 'selected' : '' }}>{{ $y }} Q1 (Jan - Mar)</option>
                                <option value="{{ $y }}-Q2" {{ $quarter === $y . '-Q2' ? 'selected' : '' }}>{{ $y }} Q2 (Apr - Jun)</option>
                                <option value="{{ $y }}-Q3" {{ $quarter === $y . '-Q3' ? 'selected' : '' }}>{{ $y }} Q3 (Jul - Sep)</option>
                                <option value="{{ $y }}-Q4" {{ $quarter === $y . '-Q4' ? 'selected' : '' }}>{{ $y }} Q4 (Oct - Dec)</option>
                            @endfor
                        </select>
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
                    <form method="POST" action="{{ route('reports.quarterly-lvr.export', ['quarter' => $quarter]) }}">
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

        {{-- Quarter Header Info --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-[#0a0a0a]">Quarterly Large Value Report</h2>
                    <p class="text-sm text-[#666666]">
                        Reporting Period: {{ $reportData['quarter_label'] ?? $quarter }}
                        ({{ $reportData['date_range']['start'] ?? '' }} - {{ $reportData['date_range']['end'] ?? '' }})
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-[#999999]">Generated: {{ $reportData['generated_at'] ?? now()->format('d M Y H:i') }}</p>
                </div>
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Transactions</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Volume (MYR)</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['total_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Average Value</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">{{ number_format($reportData['average_value'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Report Status</p>
                <p class="text-2xl font-semibold text-green-600">Complete</p>
            </div>
        </div>

        {{-- Monthly Breakdown --}}
        @if(!empty($reportData['monthly_breakdown']))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-[#0a0a0a] mb-4">Monthly Breakdown</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Month</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Transactions</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Volume (MYR)</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Average (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['monthly_breakdown'] as $month)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#0a0a0a] font-medium">{{ $month['label'] }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($month['transaction_count']) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($month['volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($month['average'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Currency Breakdown --}}
        @if(!empty($reportData['currency_breakdown']))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-[#0a0a0a] mb-4">Currency Breakdown</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Transactions</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Buy Volume</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Sell Volume</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Total Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['currency_breakdown'] as $currency)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#0a0a0a] font-medium">{{ $currency['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['transaction_count']) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['buy_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['sell_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($currency['total_volume'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- High Value Transactions --}}
        @if(!empty($reportData['high_value_transactions']))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-[#0a0a0a] mb-4">High Value Transactions (Top 20)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Date</th>
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Reference</th>
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Amount</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">MYR Equivalent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['high_value_transactions'] as $txn)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#333333]">{{ $txn['date'] }}</td>
                            <td class="py-3 px-4 text-[#333333]">{{ $txn['reference'] }}</td>
                            <td class="py-3 px-4 text-[#333333]">{{ $txn['currency'] }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($txn['amount'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($txn['myr_equivalent'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Footer Note --}}
        <div class="bg-[#fafafa] border border-[#e5e5e5] rounded-xl p-4">
            <p class="text-xs text-[#666666]">
                <strong class="text-[#333333]">Regulatory Note:</strong>
                This report includes all transactions equal to or exceeding RM 50,000 in MYR equivalent.
                Report generated in compliance with Bank Negara Malaysia quarterly reporting requirements for money service businesses.
            </p>
        </div>

        @elseif($reportGenerated && empty($reportData))
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">No Report Data</h3>
            <p class="text-sm text-[#666666]">No high-value transactions found for the selected quarter.</p>
        </div>
        @else
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">Select a Quarter</h3>
            <p class="text-sm text-[#666666]">Choose a quarter above to generate the LVR report.</p>
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
        // Auto-submit form when quarter changes
        document.getElementById('quarter').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>