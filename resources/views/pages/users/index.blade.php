<x-app-layout title="User Management">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Users</h1>
            <a href="{{ route('users.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                Add User
            </a>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users ?? [] as $user)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->username }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <x-badge variant="{{ $user->role->value === 'admin' ? 'purple' : ($user->role->value === 'manager' ? 'info' : ($user->role->value === 'compliance_officer' ? 'warning' : 'gray')) }}">
                                {{ $user->role->label() }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3">{{ $user->branch->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $user) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <x-empty-state message="No users found." :colspan="6" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>