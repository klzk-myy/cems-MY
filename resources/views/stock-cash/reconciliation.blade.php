<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Till Reconciliation - {{ $date }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Till Reconciliation</h1>
                <p class="mt-1 text-sm text-gray-500">Date: {{ $date }} | Till ID: {{ $tillId }}</p>
            </div>
            <a href="{{ url()->previous() }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                Back
            </a>
        </div>

        <!-- Opening Balance Card -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Opening Balance</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">MYR Opening</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">
                        MYR {{ number_format((float) ($reconciliation['opening_myr'] ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">FCY Opening</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">
                        {{ number_format((float) ($reconciliation['opening_fcy'] ?? 0), 2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Opening By</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $reconciliation['opener_name'] ?? 'N/A' }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Buy/Sell Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Buy Summary -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Buy Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Buys</span>
                        <span class="text-sm font-medium text-gray-900">{{ $summary['total_buys'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">FCY Bought</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ number_format((float) ($summary['fcy_bought'] ?? 0), 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-[#e5e5e5] pt-3">
                        <span class="text-sm font-medium text-gray-700">MYR Received</span>
                        <span class="text-sm font-semibold text-gray-900">
                            MYR {{ number_format((float) ($summary['myr_from_buys'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sell Summary -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Sell Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Sells</span>
                        <span class="text-sm font-medium text-gray-900">{{ $summary['total_sells'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">FCY Sold</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ number_format((float) ($summary['fcy_sold'] ?? 0), 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center border-t border-[#e5e5e5] pt-3">
                        <span class="text-sm font-medium text-gray-700">MYR Paid Out</span>
                        <span class="text-sm font-semibold text-gray-900">
                            MYR {{ number_format((float) ($summary['myr_to_sells'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expected vs Actual Closing -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Expected vs Actual Closing</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e5e5e5]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Closing</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual Closing</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($reconciliation['currency_reconciliation'] ?? [] as $currencyRecon)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $currencyRecon['currency_code'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                    {{ number_format((float) $currencyRecon['expected'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                    {{ number_format((float) $currencyRecon['actual'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    @php
                                        $variance = (float) $currencyRecon['variance'];
                                        $isYellow = $variance != 0 && abs($variance) < 100;
                                        $isRed = abs($variance) >= 100;
                                    @endphp
                                    <span class="@if($isRed) text-red-600 font-semibold @elseif($isYellow) text-yellow-600 font-medium @else text-gray-900 @endif">
                                        {{ number_format($variance, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($variance == 0)
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                            Balanced
                                        </span>
                                    @elseif(abs($variance) < 100)
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">
                                            Warning
                                        </span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">
                                            Variance
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No currency reconciliation data available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Variance Summary -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Variance Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <dt class="text-sm font-medium text-gray-500">Total MYR Variance</dt>
                    <dd class="mt-1 text-lg font-semibold @if(($reconciliation['total_myr_variance'] ?? 0) != 0) text-red-600 @else text-green-600 @endif">
                        MYR {{ number_format((float) ($reconciliation['total_myr_variance'] ?? 0), 2) }}
                    </dd>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <dt class="text-sm font-medium text-gray-500">Total FCY Variance</dt>
                    <dd class="mt-1 text-lg font-semibold @if(($reconciliation['total_fcy_variance'] ?? 0) != 0) text-red-600 @else text-green-600 @endif">
                        {{ number_format((float) ($reconciliation['total_fcy_variance'] ?? 0), 2) }}
                    </dd>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <dt class="text-sm font-medium text-gray-500">Reconciliation Status</dt>
                    <dd class="mt-1 text-sm">
                        @if(($reconciliation['is_balanced'] ?? false))
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                Balanced
                            </span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">
                                Out of Balance
                            </span>
                        @endif
                    </dd>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Transactions</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e5e5e5]">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">FCY Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">MYR Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $transaction->id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $transaction->created_at->format('H:i:s') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                        @if($transaction->transaction_type->value === 'buy')
                                            bg-blue-100 text-blue-700
                                        @else
                                            bg-purple-100 text-purple-700
                                        @endif">
                                        {{ ucfirst($transaction->transaction_type->value) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $transaction->customer->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                    {{ number_format((float) $transaction->foreign_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                    {{ number_format((float) $transaction->exchange_rate, 4) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">
                                    {{ number_format((float) $transaction->myr_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded
                                        @if($transaction->status->value === 'completed')
                                            bg-green-100 text-green-700
                                        @elseif($transaction->status->value === 'pending_approval')
                                            bg-yellow-100 text-yellow-700
                                        @else
                                            bg-gray-100 text-gray-700
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $transaction->status->value)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No transactions found for this till and date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
