<x-app-layout title="{{ ucfirst(Users Edit) }} User">
    <x-page-header title="{{ ucfirst(Users Edit) }} User" description="User management" />
    <x-card>
        @if(Users Edit === 'index')
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted" colspan="5">
                            <x-empty-state title="No users found" description="Add your first user to get started." />
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        @elseif(Users Edit === 'create' || Users Edit === 'edit')
            <form method="POST" class="space-y-4">
                @csrf
                <x-input name="name" label="Full Name" :required="true" />
                <x-input name="email" label="Email" type="email" :required="true" />
                <x-select name="role" label="Role" :options="['admin' => 'Admin', 'manager' => 'Manager', 'teller' => 'Teller']" :required="true" />
                <x-checkbox name="is_active" label="Active" :checked="true" />
                <div class="flex justify-end gap-3">
                    <x-button type="submit" variant="primary">Save</x-button>
                </div>
            </form>
        @else
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-ink-muted">Name</dt><dd class="font-medium text-ink">John Doe</dd></div>
                <div><dt class="text-ink-muted">Email</dt><dd class="font-medium text-ink">john@example.com</dd></div>
            </dl>
        @endif
    </x-card>
</x-app-layout>
