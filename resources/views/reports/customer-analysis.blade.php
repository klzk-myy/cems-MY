<x-app-layout title="Top Customer Analysis">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Top Customer Analysis</h1>
            <p class="text-sm text-gray-500 mt-1">Top 50 customers by transaction count and volume with risk ratings</p>
        </div>

        <!-- Risk Distribution Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Analyzed</div>
                <div class="text-2xl font-semibold text-gray-900">{{ number_format($riskDistribution['total']) }}</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">High Risk</div>
                <div class="text-2xl font-semibold text-red-600">{{ number_format($riskDistribution['high']) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['high'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Medium Risk</div>
                <div class="text-2xl font-semibold text-yellow-600">{{ number_format($riskDistribution['medium']) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['medium'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Low Risk</div>
                <div class="text-2xl font-semibold text-green-600">{{ number_format($riskDistribution['low']) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['low'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
            </div>
        </div>

        <!-- Top Customers Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-gray-900">Top 50 Customers</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">ID Number</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Transactions</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Total Volume (MYR)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Value</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Risk Rating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse($topCustomers as $index => $customer)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $index + 1 }}</td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $customer['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $customer['customer_code'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $customer['id_number'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($customer['transaction_count']) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($customer['total_volume'], 2) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-right">{{ number_format($customer['avg_value'], 2) }}</td>
                            <td class="px-6 py-4 text-center">
                                @switch($customer['risk_rating'])
                                    @case('high')
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">High</span>
                                        @break
                                    @case('medium')
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Medium</span>
                                        @break
                                    @case('low')
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Low</span>
                                        @break
                                    @default
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Unknown</span>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">No customer data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>