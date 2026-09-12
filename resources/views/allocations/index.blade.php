<x-app-layout title="Allocations">
    <div class="space-y-6">
        <x-page-header title="Allocations" description="Teller currency allocation management">
            <x-slot:actions>
                <x-button href="{{ route('allocations.index', ['status' => 'active']) }}" variant="secondary">Active</x-button>
                <x-button href="{{ route('allocations.index', ['status' => 'pending']) }}" variant="secondary">Pending</x-button>
                <x-button href="{{ route('allocations.index', ['status' => 'completed']) }}" variant="secondary">Completed</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($allocations as $allocation)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3">{{ $allocation->id }}</td>
                            <td class="px-4 py-3">{{ $allocation->user?->username }}</td>
                            <td class="px-4 py-3">{{ $allocation->currency?->code }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $allocation->allocated_amount, 4) }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge variant="{{ $allocation->status->value === 'Active' ? 'success' : ($allocation->status->value === 'Pending' ? 'warning' : 'info') }}">
                                    {{ $allocation->status->value }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button href="{{ route('allocations.show', $allocation->id) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No allocations found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $allocations->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
