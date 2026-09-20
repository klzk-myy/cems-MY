<x-app-layout title="Stock Transfers">
    <div class="space-y-6">
        <x-page-header title="Stock Transfers">
            @if(auth()->user()?->role->canPerform(\App\Enums\Permission::ManageStockTransfers) ?? false)
                <x-slot:actions>
                    <x-button variant="primary" href="{{ route('stock-transfers.create') }}">
                        New Transfer
                    </x-button>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">From</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">To</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($transfers ?? [] as $transfer)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">{{ $transfer->transfer_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $transfer->source_branch_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $transfer->destination_branch_name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge variant="{{ $transfer->status === \App\Enums\StockTransferStatus::Completed ? 'success' : ($transfer->status === \App\Enums\StockTransferStatus::Requested ? 'warning' : 'gray') }}">
                                    {{ $transfer->status->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $transfer->created_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ route('stock-transfers.show', $transfer) }}">
                                    View
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No transfers found." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $transfers->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
