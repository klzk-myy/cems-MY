<x-app-layout title="Customers">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Customers</h1>
                <p class="text-gray-500 text-sm mt-1">Manage customer records</p>
            </div>
            <a href="{{ route('customers.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                Add Customer
            </a>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden mb-6">
            <form method="GET" class="p-4 border-b border-[#e5e5e5]">
                <div class="grid grid-cols-4 gap-4">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or ID..." class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <select name="risk_level" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="">All Risk Levels</option>
                        <option value="low" {{ request('risk_level') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="medium" {{ request('risk_level') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="high" {{ request('risk_level') === 'high' ? 'selected' : '' }}>High</option>
                    </select>
                    <select name="nationality" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="">All Nationalities</option>
                        <option value="MY" {{ request('nationality') === 'MY' ? 'selected' : '' }}>Malaysian</option>
                        <option value="SG" {{ request('nationality') === 'SG' ? 'selected' : '' }}>Singaporean</option>
                        <option value="OTHER" {{ request('nationality') === 'OTHER' ? 'selected' : '' }}>Other</option>
                    </select>
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Filter
                    </button>
                </div>
            </form>

            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">ID Type</th>
                        <th class="px-4 py-3">ID Number</th>
                        <th class="px-4 py-3">Nationality</th>
                        <th class="px-4 py-3">Risk Level</th>
                        <th class="px-4 py-3">Last Transaction</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers ?? [] as $customer)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $customer->id_type }}</td>
                        <td class="px-4 py-3 text-sm">{{ $customer->id_number_masked }}</td>
                        <td class="px-4 py-3 text-sm">{{ $customer->nationality }}</td>
                        <td class="px-4 py-3">
                            @if(($customer->risk_level ?? '') === 'Low')
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Low</span>
                            @elseif(($customer->risk_level ?? '') === 'Medium')
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Medium</span>
                            @else
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">High</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($customer->last_transaction_at)
                                {{ $customer->last_transaction_at->format('d M Y') }}
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a href="{{ route('customers.show', $customer) }}" class="text-blue-600 hover:underline text-sm">View</a>
                                <a href="{{ route('customers.edit', $customer) }}" class="text-gray-600 hover:underline text-sm">Edit</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No customers found.</td>
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