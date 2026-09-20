<x-app-layout title="Transactions">
    <x-page-header title="Transactions" description="Manage all exchange transactions">
        <x-slot:actions>
            @if(auth()->user()->role->canPerform(\App\Enums\Permission::ManageTransactions))
                <x-button href="{{ route('transactions.batch-upload') }}" variant="secondary">Batch Upload</x-button>
                <x-button href="{{ route('transactions.export.form') }}" variant="secondary">Export</x-button>
            @endif
            @can('create', \App\Models\Transaction::class)
                <a href="{{ route('transactions.create') }}">
                    <x-button variant="primary">New Transaction</x-button>
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-input name="search" placeholder="Search ref / purpose..." :value="request('search')" class="w-48" />
            <x-select name="type" :options="$typeOptions" placeholder="All Types" :value="request('type')" class="w-32" />
            <x-select name="currency_code" :options="$currencyOptions" placeholder="All Currencies" :value="request('currency_code')" class="w-36" />
            <x-select name="status" :options="$statusOptions" placeholder="All Statuses" :value="request('status')" class="w-44" />
            <x-input name="date_from" type="date" label="From" :value="request('date_from')" inline class="w-40" />
            <x-input name="date_to" type="date" label="To" :value="request('date_to')" inline class="w-40" />
            <x-select name="is_refund" :options="['1' => 'Refunds only', '0' => 'Excl. refunds']" placeholder="All Records" :value="request('is_refund')" class="w-36" />
            <x-button type="submit" variant="secondary">Filter</x-button>
            <x-button href="{{ route('transactions.index') }}" variant="ghost">Clear</x-button>
        </form>
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
                        <td class="px-4 py-3 font-mono text-sm">
                            {{ $transaction->reference }}
                            @if($transaction->is_refund)
                                <span class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-warning/10 text-warning">Refund</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-customer-link :customer="$transaction->customer" /></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $transaction->type?->value === 'Buy' ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                {{ $transaction->type->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($transaction->amount_myr, 2) }} {{ $transaction->currency_code }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $transaction->status === \App\Enums\TransactionStatus::Completed ? 'bg-success/10 text-success' : (in_array($transaction->status, [\App\Enums\TransactionStatus::PendingApproval, \App\Enums\TransactionStatus::PendingCancellation], true) ? 'bg-warning/10 text-warning' : 'bg-gray/10 text-muted') }}">
                                {{ $transaction->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $transaction->created_at->format('M j, Y H:i') }}</td>
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
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
