<x-app-layout title="Stock Transfer #{{ $stockTransfer->id }}">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Stock Transfer #{{ $stockTransfer->id }}"
            :description="($stockTransfer->sourceBranch->code ?? 'N/A') . ' → ' . ($stockTransfer->destinationBranch->code ?? 'N/A')"
            :actions="true"
        >
            <x-slot:actions>
                <x-button href="{{ route('stock-transfers.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Transfer Details">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Transfer Number</dt>
                        <dd class="mt-1 text-sm text-ink">#{{ $stockTransfer->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Status</dt>
                        <dd class="mt-1 text-sm">
                            <x-badge variant="{{ $stockTransfer->status->value === 'completed' ? 'success' : ($stockTransfer->status->value === 'pending' ? 'warning' : ($stockTransfer->status->value === 'cancelled' ? 'danger' : 'info')) }}">
                                {{ ucfirst(str_replace('_', ' ', $stockTransfer->status->value)) }}
                            </x-badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Source Branch</dt>
                        <dd class="mt-1 text-sm text-ink">
                            {{ $stockTransfer->sourceBranch->code ?? 'N/A' }} - {{ $stockTransfer->sourceBranch->name ?? 'N/A' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Destination Branch</dt>
                        <dd class="mt-1 text-sm text-ink">
                            {{ $stockTransfer->destinationBranch->code ?? 'N/A' }} - {{ $stockTransfer->destinationBranch->name ?? 'N/A' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Requested By</dt>
                        <dd class="mt-1 text-sm text-ink">
                            {{ $stockTransfer->requestedBy->name ?? 'N/A' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-ink-muted">Request Date</dt>
                        <dd class="mt-1 text-sm text-ink">
                            {{ $stockTransfer->created_at->format('d M Y H:i:s') }}
                        </dd>
                    </div>
                </div>

                @if($stockTransfer->notes)
                    <div class="mt-6 pt-6 border-t border-border">
                        <dt class="text-sm font-medium text-ink-muted mb-2">Notes</dt>
                        <dd class="text-sm text-ink-muted">{{ $stockTransfer->notes }}</dd>
                    </div>
                @endif
            </div>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Branch Manager Approval">
                <div class="p-6 space-y-3">
                    @if($stockTransfer->branchManagerApprovedBy)
                        <div class="flex items-center gap-2 text-success-text">
                            <x-badge variant="success">Approved</x-badge>
                        </div>
                        <p class="text-sm text-ink-muted">By: {{ $stockTransfer->branchManagerApprovedBy->name ?? 'N/A' }}</p>
                        <p class="text-sm text-ink-muted">
                            {{ $stockTransfer->branch_manager_approved_at?->format('d M Y H:i:s') ?? 'N/A' }}
                        </p>
                    @else
                        <div class="flex items-center gap-2 text-ink-muted">
                            <x-badge variant="gray">Pending</x-badge>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card title="HQ Approval">
                <div class="p-6 space-y-3">
                    @if($stockTransfer->hqApproval)
                        <div class="flex items-center gap-2 text-success-text">
                            <x-badge variant="success">Approved</x-badge>
                        </div>
                        <p class="text-sm text-ink-muted">By: {{ $stockTransfer->hqApproval->name ?? 'N/A' }}</p>
                        <p class="text-sm text-ink-muted">
                            {{ $stockTransfer->hq_approved_at?->format('d M Y H:i:s') ?? 'N/A' }}
                        </p>
                    @else
                        <div class="flex items-center gap-2 text-ink-muted">
                            <x-badge variant="gray">Pending</x-badge>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>

        <x-card title="Transfer Items">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @forelse($stockTransfer->items as $item)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm text-ink">
                                        {{ $item->currency->code ?? 'N/A' }} - {{ $item->currency->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink text-right">
                                        {{ number_format((float) $item->amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge variant="{{ $item->status->value === 'transferred' ? 'success' : ($item->status->value === 'pending' ? 'warning' : 'gray') }}">
                                            {{ ucfirst($item->status->value) }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state message="No items in this transfer." :colspan="3" />
                            @endforelse
                        </x-slot:tbody>
                    </x-table>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="p-6">
                <div class="flex flex-wrap items-center gap-3">
                    @if($stockTransfer->canApproveBranchManager())
                        <form action="{{ route('stock-transfers.approve-bm', $stockTransfer->id) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" variant="info">Approve (Branch Manager)</x-button>
                        </form>
                    @endif

                    @if($stockTransfer->canApproveHq())
                        <form action="{{ route('stock-transfers.approve-hq', $stockTransfer->id) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" variant="indigo">Approve (HQ)</x-button>
                        </form>
                    @endif

                    @if($stockTransfer->canDispatch())
                        <form action="{{ route('stock-transfers.dispatch', $stockTransfer->id) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" variant="purple">Dispatch</x-button>
                        </form>
                    @endif

                    @if($stockTransfer->canReceive())
                        <form action="{{ route('stock-transfers.receive', $stockTransfer->id) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" variant="teal">Receive</x-button>
                        </form>
                    @endif

                    @if($stockTransfer->canComplete())
                        <form action="{{ route('stock-transfers.complete', $stockTransfer->id) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" variant="success">Complete</x-button>
                        </form>
                    @endif

                    @if($stockTransfer->canCancel())
                        <form action="{{ route('stock-transfers.cancel', $stockTransfer->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Are you sure you want to cancel this stock transfer?');">
                            @csrf
                            <x-button type="submit" variant="danger">Cancel</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
