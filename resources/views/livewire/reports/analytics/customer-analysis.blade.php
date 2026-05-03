<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Top Customers by Transaction Volume</h2>
        </div>

        <div class="p-6">
            @if(empty($topCustomers))
                <div class="text-center py-8 text-gray-500">
                    No customer data available.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Volume (MYR)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Transaction</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">First</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Last</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Rating</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($topCustomers as $cust)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $cust['full_name'] }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($cust['transaction_count']) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right font-medium">{{ number_format((float)$cust['total_volume'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format((float)$cust['avg_transaction'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ $cust['first_transaction'] ? \Carbon\Carbon::parse($cust['first_transaction'])->format('d M Y') : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ $cust['last_transaction'] ? \Carbon\Carbon::parse($cust['last_transaction'])->format('d M Y') : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($cust['risk_rating'] === 'High') bg-red-100 text-red-800
                                            @elseif($cust['risk_rating'] === 'Medium') bg-yellow-100 text-yellow-800
                                            @elseif($cust['risk_rating'] === 'Low') bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ $cust['risk_rating'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if(!empty($riskDistribution))
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Risk Distribution</h2>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap gap-4">
                    @foreach($riskDistribution as $dist)
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                                @if($dist['risk_rating'] === 'High') bg-red-100 text-red-800
                                @elseif($dist['risk_rating'] === 'Medium') bg-yellow-100 text-yellow-800
                                @elseif($dist['risk_rating'] === 'Low') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $dist['risk_rating'] ?? 'Unknown' }}: {{ $dist['count'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
