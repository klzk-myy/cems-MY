<x-app-layout title="Balance Sheet">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Balance Sheet</h1>
                <p class="mt-1 text-sm text-gray-500">As of {{ $asOfDate }}</p>
            </div>
            <form method="GET" class="flex items-center gap-3">
                <input type="date" name="as_of_date" value="{{ $asOfDate }}"
                       class="px-3 py-2 text-sm border border-[#e5e5e5] rounded-lg">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Refresh
                </button>
            </form>
        </div>

        @php
            $assets = $balanceSheet['assets'] ?? [];
            $liabilities = $balanceSheet['liabilities'] ?? [];
            $equity = $balanceSheet['equity'] ?? [];
            $totalAssets = $balanceSheet['total_assets'] ?? '0';
            $totalLiabilities = $balanceSheet['total_liabilities'] ?? '0';
            $totalEquity = $balanceSheet['total_equity'] ?? '0';
            $totalLiabilitiesEquity = $balanceSheet['total_liabilities_equity'] ?? '0';
            $isBalanced = $balanceSheet['is_balanced'] ?? false;
        @endphp

        @if ($isBalanced)
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm font-medium text-green-800">✓ Balance Sheet is balanced</p>
                <p class="text-sm text-green-600">Assets: RM {{ number_format((float) $totalAssets, 2) }} = Liabilities + Equity: RM {{ number_format((float) $totalLiabilitiesEquity, 2) }}</p>
            </div>
        @else
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm font-medium text-red-800">✗ Balance Sheet is NOT balanced</p>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-[#e5e5e5] bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-900">Assets</h3>
                </div>
                <table class="w-full">
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse ($assets as $asset)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $asset['account_code'] }} - {{ $asset['account_name'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $asset['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-gray-500">No assets</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                        <tr class="font-semibold">
                            <td class="px-4 py-3 text-sm text-gray-900">Total Assets</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalAssets, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#e5e5e5] bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-900">Liabilities</h3>
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-[#e5e5e5]">
                            @forelse ($liabilities as $liability)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $liability['account_code'] }} - {{ $liability['account_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $liability['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-gray-500">No liabilities</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                            <tr class="font-semibold">
                                <td class="px-4 py-3 text-sm text-gray-900">Total Liabilities</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalLiabilities, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-[#e5e5e5] bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-900">Equity</h3>
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-[#e5e5e5]">
                            @forelse ($equity as $eq)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $eq['account_code'] }} - {{ $eq['account_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $eq['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-3 text-sm text-center text-gray-500">No equity accounts</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                            <tr class="font-semibold">
                                <td class="px-4 py-3 text-sm text-gray-900">Total Equity</td>
                                <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $totalEquity, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">Total Liabilities & Equity</p>
                        <p class="text-lg font-semibold text-gray-900">RM {{ number_format((float) $totalLiabilitiesEquity, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
