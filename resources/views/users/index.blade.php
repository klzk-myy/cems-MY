<x-app-layout title="Users Index User">
    <x-page-header title="Users Index User" description="User management" />
    <x-card>
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
    </x-card>
</x-app-layout>
