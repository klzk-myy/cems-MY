<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Users</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Manage system users and permissions</p>
        </div>
        <a href="{{ route('users.create') }}" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Add User</a>
    </div>

    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden mb-6">
        <div class="p-4 border-b border-[#e5e5e5] bg-[#f7f7f8]">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" wire:model.live="search" placeholder="Search by username or email..." class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                </div>
                <div class="w-48">
                    <select wire:model.live="roleFilter" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">All Roles</option>
                        <option value="teller">Teller</option>
                        <option value="manager">Manager</option>
                        <option value="compliance_officer">Compliance Officer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="w-40">
                    <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if($users->isEmpty())
                <div class="text-center py-8 text-[#6b6b6b]">No users found.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Username</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Email</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Role</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Created</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e5e5]">
                            @foreach($users as $user)
                                <tr class="hover:bg-[#f7f7f8]/50">
                                    <td class="px-4 py-3 text-sm font-medium text-[#171717]">
                                        <a href="{{ route('users.show', $user) }}" class="text-[#d4a843] hover:underline">{{ $user->username }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#6b6b6b]">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($user->role->value === 'admin') bg-purple-100 text-purple-800
                                            @elseif($user->role->value === 'manager') bg-blue-100 text-blue-800
                                            @elseif($user->role->value === 'compliance_officer') bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ $user->role->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#6b6b6b]">{{ $user->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('users.edit', $user) }}" class="text-[#d4a843] hover:underline text-sm">Edit</a>
                                            <button wire:click="toggleActive({{ $user->id }})" class="text-sm {{ $user->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
                        @if($users->onFirstPage())
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#6b6b6b] border border-[#e5e5e5] rounded-l-md">Previous</span>
                        @else
                            <button wire:click="previousPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-l-md hover:bg-[#f7f7f8]">Previous</button>
                        @endif

                        @foreach($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                            @if($page == $users->currentPage())
                                <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-[#0a0a0a] border border-[#0a0a0a]">{{ $page }}</span>
                            @else
                                <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] hover:bg-[#f7f7f8]">{{ $page }}</button>
                            @endif
                        @endforeach

                        @if($users->hasMorePages())
                            <button wire:click="nextPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-r-md hover:bg-[#f7f7f8]">Next</button>
                        @else
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#6b6b6b] border border-[#e5e5e5] rounded-r-md">Next</span>
                        @endif
                    </nav>
                </div>
            @endif
        </div>
    </div>
</div>
