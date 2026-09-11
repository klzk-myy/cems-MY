<x-app-layout title="Top Customer Analysis">
    <x-page-header title="Top Customer Analysis" description="Top 50 customers by transaction volume" />

    <x-card class="mt-4 !p-0">
        @if (!empty($topCustomers))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Customer</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Code</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">ID Number</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Transactions</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Total Volume</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Avg Value</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Risk Rating</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">First Transaction</th>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Last Transaction</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($topCustomers as $customer)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ $customer['name'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $customer['customer_code'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $customer['id_number'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($customer['transaction_count'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($customer['total_volume'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($customer['avg_value'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="match (strtolower($customer['risk_rating'] ?? '')) {
                                        'high' => 'error',
                                        'medium' => 'warning',
                                        'low' => 'success',
                                        default => 'gray',
                                    }">{{ $customer['risk_rating'] ?? '—' }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">{{ $customer['first_transaction'] ? date('Y-m-d', strtotime($customer['first_transaction'])) : '—' }}</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $customer['last_transaction'] ? date('Y-m-d', strtotime($customer['last_transaction'])) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No customer transaction data found." />
        @endif
    </x-card>
</x-app-layout>

