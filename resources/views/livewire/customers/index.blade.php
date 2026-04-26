<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Customer Management</h2>
                <a href="{{ route('customers.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Add New Customer
                </a>
            </div>
        </div>

        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" wire:model.live="search" placeholder="Search by name..." class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="w-36">
                    <select wire:model.live="riskRating" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Risk</option>
                        @foreach($riskRatings as $rating)
                            <option value="{{ $rating }}">{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <select wire:model.live="statusFilter" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="w-28">
                    <select wire:model.live="pepFilter" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">PEP?</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="w-40">
                    <select wire:model.live="nationalityFilter" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Nations</option>
                        @foreach($nationalities as $nation)
                            <option value="{{ $nation }}">{{ $nation }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if($customers->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    No customers found.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Type</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Risk</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">PEP</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($customers as $customer)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:text-indigo-900">{{ $customer->full_name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $customer->id_type }} - {{ $customer->ic_number ? substr($customer->ic_number, 0, 4).'****'.substr($customer->ic_number, -4) : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($customer->risk_rating === 'High') bg-red-100 text-red-800
                                            @elseif($customer->risk_rating === 'Medium') bg-yellow-100 text-yellow-800
                                            @else bg-green-100 text-green-800 @endif">
                                            {{ $customer->risk_rating ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($customer->pep_status)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Yes</span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">No</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ $customer->transactions_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $customer->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                                            <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
                        @if($customers->onFirstPage())
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 border border-gray-300 rounded-l-md">Previous</span>
                        @else
                            <button wire:click="previousPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</button>
                        @endif

                        @foreach($customers->getUrlRange(1, $customers->lastPage()) as $page => $url)
                            @if($page == $customers->currentPage())
                                <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-indigo-600">{{ $page }}</span>
                            @else
                                <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">{{ $page }}</button>
                            @endif
                        @endforeach

                        @if($customers->hasMorePages())
                            <button wire:click="nextPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">Next</button>
                        @else
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 border border-gray-300 rounded-r-md">Next</span>
                        @endif
                    </nav>
                </div>
            @endif
        </div>
    </div>
</div>