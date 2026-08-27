<x-app-layout title="Customers">
    <x-page-header title="Customers" description="Manage customer records">
        <x-slot:actions>
            <a href="{{ route('customers.create') }}">
                <x-button variant="primary">Add Customer</x-button>
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar>
        <x-input name="search" placeholder="Search customers..." class="w-48" />
        <x-select name="status" :options="['' => 'All', 'active' => 'Active', 'frozen' => 'Frozen']" class="w-36" />
        <x-button variant="secondary">Filter</x-button>
    </x-filter-bar>

    <div class="mt-4">
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </x-slot:thead>
            <x-slot:tbody>
                <tr>
                    <td class="px-4 py-3 text-sm text-ink-muted" colspan="5">
                        <x-empty-state title="No customers found" description="Add your first customer to get started." />
                    </td>
                </tr>
            </x-slot:tbody>
        </x-table>
    </div>
</x-app-layout>
