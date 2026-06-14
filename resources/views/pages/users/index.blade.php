<x-app-layout title="User Management">
    <div class="space-y-6">
        <x-page-header title="Users">
            <x-slot:actions>
                <x-button href="{{ route('users.create') }}" variant="primary">Add User</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Username</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Branch</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($users ?? [] as $user)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
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
                                <x-button href="{{ route('users.show', $user) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No users found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $users->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>
