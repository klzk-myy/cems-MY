<x-app-layout title="Transactions">
    <x-page-header title="Transactions" description="Manage all exchange transactions">
        <x-slot:actions>
            <a href="{{ route('transactions.create') }}">
                <x-button variant="primary">New Transaction</x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar>
        <x-input name="search" placeholder="Search..." class="w-48" />
        <x-select name="status" :options="['' => 'All', 'pending' => 'Pending', 'completed' => 'Completed']" class="w-36" />
        <x-button variant="secondary">Filter</x-button>
    </x-filter-bar>

    <div class="mt-4">
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </x-slot:thead>
            <x-slot:tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td class="px-4 py-3 font-mono text-sm">{{ $transaction->reference }}</td>
                        <td class="px-4 py-3">{{ $transaction->customer->full_name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $transaction->type === 'buy' ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                {{ $transaction->type->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($transaction->amount_local, 2) }} {{ $transaction->currency_code }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $transaction->status === 'completed' ? 'bg-success/10 text-success' : ($transaction->status === 'pending' ? 'bg-warning/10 text-warning' : 'bg-gray/10 text-muted') }}">
                                {{ $transaction->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('transactions.show', $transaction) }}" class="text-primary hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted" colspan="7">
                            <x-empty-state title="No transactions found" description="Create your first transaction to get started." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:tbody>
        </x-table>
    </div>
</x-app-layout>
