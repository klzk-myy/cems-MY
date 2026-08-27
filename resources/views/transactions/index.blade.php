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
                <tr>
                    <td class="px-4 py-3 text-sm text-ink-muted" colspan="7">
                        <x-empty-state title="No transactions found" description="Create your first transaction to get started." />
                    </td>
                </tr>
            </x-slot:tbody>
        </x-table>
    </div>
</x-app-layout>
