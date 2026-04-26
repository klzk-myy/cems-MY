<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">User Details</h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('users.edit', $user) }}" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-lg hover:bg-indigo-50">
                        Edit
                    </a>
                    <a href="{{ route('users.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Username</label>
                        <p class="text-gray-900 font-medium">{{ $user->username }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Email</label>
                        <p class="text-gray-900">{{ $user->email }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Role</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                @if($user->role->value === 'admin') bg-purple-100 text-purple-800
                                @elseif($user->role->value === 'manager') bg-blue-100 text-blue-800
                                @elseif($user->role->value === 'compliance_officer') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $user->role->label() }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Status</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Branch</label>
                        <p class="text-gray-900">{{ $user->branch?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Created At</label>
                        <p class="text-gray-900">{{ $user->created_at->format('d M Y H:i') }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Last Login</label>
                        <p class="text-gray-900">{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : 'Never' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">MFA Enabled</label>
                        <p class="text-gray-900">{{ $user->mfa_enabled ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
            </div>

            @if($user->counters && $user->counters->count() > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Assigned Counters</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($user->counters as $counter)
                            <span class="inline-flex px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full">
                                {{ $counter->code }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>