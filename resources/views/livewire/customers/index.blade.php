<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Customers</h1>
            <a href="{{ route('customers.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Add Customer</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Name</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Email</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Phone</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $customer->name }}</td>
                        <td class="px-4 py-3">{{ $customer->email }}</td>
                        <td class="px-4 py-3">{{ $customer->phone }}</td>
                        <td class="px-4 py-3">
                            @if($customer->is_active)
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer->id) }}" class="text-blue-600 hover:underline mr-2">View</a>
                            <a href="{{ route('customers.edit', $customer->id) }}" class="text-blue-600 hover:underline mr-2">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No customers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </div>
</div>