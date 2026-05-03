<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LCTR - Large Cash Transaction Report - CEMS-MY</title>
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
                    <h1 class="text-2xl font-semibold text-gray-900">Large Cash Transaction Report (LCTR)</h1>
                    <p class="text-sm text-gray-500 mt-1">Monthly report for transactions >= RM 25,000</p>
                </div>
                @if($reportGenerated)
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-sm text-green-600">Report Generated</span>
                    </div>
                @endif
            </div>

            <!-- Month Selector -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <form method="GET" action="{{ route('reports.lctr') }}" class="flex flex-wrap gap-4 items-end">
                    <div class="flex flex-col gap-2">
                        <label for="month" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Report Month</label>
                        <input type="month" name="month" id="month" value="{{ $month }}"
                            class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900">
                    </div>
                    <button type="submit" class="px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] transition-colors">
                        Generate Report
                    </button>
                </form>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total Transactions</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['count']) }}</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Total Amount</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total_amount'], 2) }} MYR</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Unique Customers</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['unique_customers']) }}</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Avg Transaction</div>
                    <div class="text-2xl font-semibold text-gray-900">
                        {{ $stats['count'] > 0 ? number_format($stats['total_amount'] / $stats['count'], 2) : '0.00' }} MYR
                    </div>
                </div>
            </div>

            <!-- Pending Transactions Warning -->
            @if($pendingTransactions > 0)
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 flex items-center gap-3">
                    <svg class="w-5 h-5 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-yellow-700">{{ $pendingTransactions }} Pending Transactions</div>
                        <div class="text-xs text-yellow-600">Some qualifying transactions are still pending and not included in this report.</div>
                    </div>
                </div>
            @endif

            <!-- Transactions Table -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Qualifying Transactions (>= RM 25,000)</h2>
                    <p class="text-sm text-gray-500 mt-1">Report Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</p>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">ID Number</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Amount (MYR)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Type</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $transaction['transaction_ref'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ \Carbon\Carbon::parse($transaction['transaction_date'])->format('d M Y') }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $transaction['customer_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $transaction['customer_code'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $transaction['id_number'] }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 text-right">{{ number_format($transaction['amount'], 2) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $transaction['type'] === 'buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ strtoupper($transaction['type']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @switch($transaction['status'])
                                        @case('completed')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span>
                                            @break
                                        @case('pending')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                                            @break
                                        @case('pending_approval')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">Pending Approval</span>
                                            @break
                                        @case('cancelled')
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Cancelled</span>
                                            @break
                                        @default
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ ucfirst($transaction['status']) }}</span>
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No qualifying transactions found for this period
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Report Footer -->
            @if($reportGenerated && count($transactions) > 0)
                <div class="mt-6 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Generated on: {{ now()->format('d M Y H:i:s') }}
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="window.print()" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50 transition-colors">
                            Print Report
                        </button>
                        <button type="button" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] transition-colors">
                            Export PDF
                        </button>
                    </div>
                </div>
            @endif
        </main>
    </div>
</body>
</html>