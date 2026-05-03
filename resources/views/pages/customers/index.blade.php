<x-app-layout title="Customers">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Customers</h1>
            <a href="{{ route('customers.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Add Customer
            </a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">ID Type</th>
                        <th class="px-4 py-3">ID Number</th>
                        <th class="px-4 py-3">Nationality</th>
                        <th class="px-4 py-3">Risk Level</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers ?? [] as $customer)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3">{{ $customer->id_type }}</td>
                        <td class="px-4 py-3">{{ $customer->id_number }}</td>
                        <td class="px-4 py-3">{{ $customer->nationality }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs 
                                @if($customer->risk_level === 'Low') bg-green-100 text-green-800
                                @elseif($customer->risk_level === 'Medium') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ $customer->risk_level ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No customers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>