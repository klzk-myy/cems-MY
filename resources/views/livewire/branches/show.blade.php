<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    @if($branch->is_main)
                        <span class="text-indigo-600 mr-1">★</span>
                    @endif
                    {{ $branch->code }} - {{ $branch->name }}
                </h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('branches.edit', $branch) }}" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-lg hover:bg-indigo-50">
                        Edit
                    </a>
                    <a href="{{ route('branches.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Branch Code</label>
                        <p class="text-gray-900 font-medium">{{ $branch->code }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Type</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                @if($branch->type === 'head_office') bg-purple-100 text-purple-800
                                @elseif($branch->type === 'branch') bg-blue-100 text-blue-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ ucfirst(str_replace('_', ' ', $branch->type)) }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Status</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $branch->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                    @if($branch->parent)
                        <div>
                            <label class="text-sm text-gray-500">Parent Branch</label>
                            <p class="text-gray-900">
                                <a href="{{ route('branches.show', $branch->parent) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $branch->parent->code }} - {{ $branch->parent->name }}
                                </a>
                            </p>
                        </div>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Location</label>
                        <p class="text-gray-900">
                            {{ $branch->address ? $branch->address . ', ' : '' }}
                            {{ $branch->city ? $branch->city . ', ' : '' }}
                            {{ $branch->state ? $branch->state . ' ' : '' }}
                            {{ $branch->postal_code ? $branch->postal_code : '' }}
                            {{ $branch->country ? ', ' . $branch->country : '' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Phone</label>
                        <p class="text-gray-900">{{ $branch->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Email</label>
                        <p class="text-gray-900">{{ $branch->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Main Branch</label>
                        <p class="text-gray-900">{{ $branch->is_main ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Users</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['user_count'] }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Counters</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['counter_count'] }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Today's Transactions</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['transaction_today']) }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">This Month</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['transaction_month']) }}</div>
                </div>
            </div>

            @if(count($childBranches) > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Child Branches</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($childBranches as $child)
                                    <tr>
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                            <a href="{{ route('branches.show', $child['id']) }}" class="text-indigo-600 hover:text-indigo-900">{{ $child['code'] }}</a>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $child['name'] }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ ucfirst(str_replace('_', ' ', $child['type'])) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $child['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $child['is_active'] ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>