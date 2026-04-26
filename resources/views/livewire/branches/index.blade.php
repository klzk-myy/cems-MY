<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Branch Management</h2>
                <a href="{{ route('branches.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Add New Branch
                </a>
            </div>
        </div>

        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" wire:model.live="search" placeholder="Search by code or name..." class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="w-48">
                    <select wire:model.live="typeFilter" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Types</option>
                        <option value="head_office">Head Office</option>
                        <option value="branch">Branch</option>
                        <option value="sub_branch">Sub-Branch</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if($branches->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    No branches found.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($branches as $branch)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        @if($branch->is_main)
                                            <span class="text-indigo-600">★</span>
                                        @endif
                                        <a href="{{ route('branches.show', $branch) }}" class="text-indigo-600 hover:text-indigo-900">{{ $branch->code }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $branch->name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($branch->type === 'head_office') bg-purple-100 text-purple-800
                                            @elseif($branch->type === 'branch') bg-blue-100 text-blue-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst(str_replace('_', ' ', $branch->type)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $branch->city ? $branch->city . ', ' . $branch->state : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('branches.show', $branch) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                                            <a href="{{ route('branches.edit', $branch) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
                        @if($branches->onFirstPage())
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 border border-gray-300 rounded-l-md">Previous</span>
                        @else
                            <button wire:click="previousPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</button>
                        @endif

                        @foreach($branches->getUrlRange(1, $branches->lastPage()) as $page => $url)
                            @if($page == $branches->currentPage())
                                <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-indigo-600">{{ $page }}</span>
                            @else
                                <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">{{ $page }}</button>
                            @endif
                        @endforeach

                        @if($branches->hasMorePages())
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