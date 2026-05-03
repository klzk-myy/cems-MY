<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSB2 Daily Transaction Summary</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f5f5] min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-[#0a0a0a]">MSB2 Daily Transaction Summary</h1>
                    <p class="text-sm text-[#666666] mt-1">Daily Summary of Money Service Business Transactions</p>
                </div>
                @if($isToday)
                <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Today</span>
                @endif
            </div>
        </div>

        {{-- Date Selector --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('reports.msb2') }}" class="flex flex-col md:flex-row md:items-end gap-4">
                <div>
                    <label for="date" class="block text-sm font-medium text-[#333333] mb-2">Select Date</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="{{ $date }}"
                        class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0a0a0a] focus:border-transparent"
                    />
                </div>
                <button
                    type="submit"
                    class="px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                >
                    View Report
                </button>
            </form>

            @if($nextBusinessDay)
            <div class="mt-4 pt-4 border-t border-[#e5e5e5]">
                <p class="text-sm text-[#666666]">
                    Next Business Day: <span class="font-medium text-[#333333]">{{ \Carbon\Carbon::parse($nextBusinessDay)->format('d M Y (l)') }}</span>
                </p>
            </div>
            @endif
        </div>

        {{-- Report Content --}}
        @if($reportGenerated)

        {{-- Stats Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Transactions</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">{{ number_format($stats['total_transactions'] ?? 0) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Buy Volume</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">MYR {{ number_format($stats['total_buy_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Total Sell Volume</p>
                <p class="text-2xl font-semibold text-[#0a0a0a]">MYR {{ number_format($stats['total_sell_volume'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Net Position</p>
                <p class="text-2xl font-semibold {{ ($stats['net_position'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    MYR {{ number_format($stats['net_position'] ?? 0, 2) }}
                </p>
            </div>
        </div>

        {{-- Currency Breakdown Table --}}
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="border-b border-[#e5e5e5] pb-4 mb-6">
                <h2 class="text-lg font-semibold text-[#0a0a0a]">Currency Breakdown</h2>
                <p class="text-sm text-[#666666]">Transaction volumes by currency for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e5e5]">
                            <th class="text-left py-3 px-4 font-medium text-[#333333]">Currency</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Buy Count</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Buy Volume</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Sell Count</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Sell Volume</th>
                            <th class="text-right py-3 px-4 font-medium text-[#333333]">Net Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary as $currency => $data)
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#fafafa]">
                            <td class="py-3 px-4 text-[#0a0a0a] font-medium">{{ $currency }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($data['buy_count']) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($data['buy_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($data['sell_count']) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333]">{{ number_format($data['sell_volume'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#333333] {{ $data['net_volume'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($data['net_volume'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-[#666666]">No transaction data available for this date</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(!empty($summary))
                    <tfoot>
                        <tr class="bg-[#f5f5f5] font-semibold">
                            <td class="py-3 px-4 text-[#0a0a0a]">TOTAL</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($stats['total_buy_count'] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($stats['total_buy_volume'] ?? 0, 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($stats['total_sell_count'] ?? 0) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($stats['total_sell_volume'] ?? 0, 2) }}</td>
                            <td class="py-3 px-4 text-right text-[#0a0a0a]">{{ number_format($stats['net_position'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Additional Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Average Transaction Value</p>
                <p class="text-lg font-semibold text-[#0a0a0a]">MYR {{ number_format($stats['avg_transaction_value'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Pending Approval</p>
                <p class="text-lg font-semibold text-[#0a0a0a]">{{ number_format($stats['pending_approval'] ?? 0) }}</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-5">
                <p class="text-xs text-[#666666] mb-1">Report Status</p>
                <p class="text-lg font-semibold text-green-600">Complete</p>
            </div>
        </div>

        {{-- Export Actions --}}
        <div class="flex justify-end gap-3">
            <button
                onclick="window.print()"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-[#f5f5f5]"
            >
                Print Report
            </button>
            <form method="POST" action="{{ route('reports.msb2.export', ['date' => $date]) }}">
                @csrf
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                >
                    Export Report
                </button>
            </form>
        </div>

        @else
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-[#d1d1d1] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-medium text-[#333333] mb-2">Select a Date</h3>
            <p class="text-sm text-[#666666]">Choose a date above to view the MSB2 daily transaction summary.</p>
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
</body>
</html>