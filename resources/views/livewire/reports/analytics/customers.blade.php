<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Customer Analytics</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-4 gap-6 mb-6">
                <div>
                    <p class="text-sm text-gray-500">Total Customers</p>
                    <p class="text-3xl font-bold">{{ number_format($totalCustomers) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active</p>
                    <p class="text-3xl font-bold text-green-600">{{ number_format($activeCustomers) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">High Risk</p>
                    <p class="text-3xl font-bold text-red-600">{{ number_format($highRiskCustomers) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">New This Month</p>
                    <p class="text-3xl font-bold text-blue-600">{{ number_format($newCustomers) }}</p>
                </div>
            </div>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Customer</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Total Transactions</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-[var(--color-ink)]">Volume</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Risk Level</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topCustomers as $customer)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $customer['name'] }}</td>
                        <td class="px-4 py-3">{{ number_format($customer['transactions']) }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format($customer['volume'], 2) }}</td>
                        <td class="px-4 py-3">
                            @if($customer['risk'] === 'high')
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">High</span>
                            @elseif($customer['risk'] === 'medium')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Medium</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Low</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-center">No data available</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>