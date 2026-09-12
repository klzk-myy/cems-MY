<x-app-layout title="Stock Transfer">
    <div class="space-y-6">
        <x-page-header title="Stock Transfer {{ $stockTransfer->transfer_number }}">
            @php
                $statusVariant = match ($stockTransfer->status->value) {
                    'Completed', 'Received' => 'success',
                    'Requested' => 'warning',
                    'Cancelled', 'Rejected' => 'danger',
                    default => 'info',
                };
            @endphp
            <x-slot:actions>
                <x-badge variant="{{ $statusVariant }}" size="lg">{{ $stockTransfer->status->label() }}</x-badge>
                <x-button variant="secondary" href="{{ route('stock-transfers.index') }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Transfer Details">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-sm">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Reference</label>
                    <p class="font-mono text-ink">{{ $stockTransfer->transfer_number }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Type</label>
                    <p class="text-ink">{{ $stockTransfer->type }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Source Branch</label>
                    <p class="text-ink">{{ $stockTransfer->source_branch_name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Destination Branch</label>
                    <p class="text-ink">{{ $stockTransfer->destination_branch_name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Requested By</label>
                    <p class="text-ink">{{ $stockTransfer->requestedBy?->username ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Requested At</label>
                    <p class="text-ink">{{ $stockTransfer->requested_at?->format('Y-m-d H:i') ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Total Value (MYR)</label>
                    <p class="text-ink font-medium">RM {{ number_format((float) $stockTransfer->total_value_myr, 2) }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Created</label>
                    <p class="text-ink">{{ $stockTransfer->created_at?->format('M d, Y H:i') }}</p>
                </div>
            </div>

            @if($stockTransfer->notes)
                <div class="mt-4 pt-4 border-t border-border">
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Notes</label>
                    <p class="text-sm text-ink">{{ $stockTransfer->notes }}</p>
                </div>
            @endif

            @if($stockTransfer->cancellation_reason)
                <div class="mt-4 pt-4 border-t border-border">
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Cancellation Reason</label>
                    <p class="text-sm text-danger-text">{{ $stockTransfer->cancellation_reason }}</p>
                </div>
            @endif
        </x-card>

        <x-card title="Items">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Quantity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Rate</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Value (MYR)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Received</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">In Transit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Variance</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($stockTransfer->items as $item)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">{{ $item->currency_code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->rate }}</td>
                            <td class="px-4 py-3 text-sm">RM {{ number_format((float) $item->value_myr, 2) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->quantity_received ?? '0' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->quantity_in_transit ?? '0' }}</td>
                            <td class="px-4 py-3 text-sm {{ $item->hasVariance() ? 'text-danger-text font-medium' : 'text-ink-muted' }}">
                                {{ $item->variance }}
                                @if($item->variance_notes)
                                    <span class="block text-xs text-ink-muted">{{ $item->variance_notes }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No items on this transfer." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap items-center gap-3">
                @if($stockTransfer->canApproveBranchManager())
                    @can('approveBranchManager', $stockTransfer)
                        <form action="{{ route('stock-transfers.approve-bm', $stockTransfer->id) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="primary">Approve (Destination Branch)</x-button>
                        </form>
                    @endcan
                @endif

                @if($stockTransfer->canDispatch())
                    @can('dispatch', $stockTransfer)
                        <form action="{{ route('stock-transfers.dispatch', $stockTransfer->id) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="primary">Dispatch</x-button>
                        </form>
                    @endcan
                @endif

                @if($stockTransfer->canComplete())
                    @can('complete', $stockTransfer)
                        <form action="{{ route('stock-transfers.complete', $stockTransfer->id) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="primary">Complete Transfer</x-button>
                        </form>
                    @endcan
                @endif

                @if($stockTransfer->canCancel())
                    @can('cancel', $stockTransfer)
                    <div x-data="{ showCancelModal: false }" class="inline">
                        <x-button @click="showCancelModal = true" variant="danger">Cancel</x-button>

                        <div x-show="showCancelModal"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @keydown.escape.window="showCancelModal = false"
                             @click="showCancelModal = false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                             role="dialog"
                             aria-modal="true"
                             aria-labelledby="cancel-modal-title">
                            <div class="bg-surface rounded-xl shadow-lg max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                                <div class="flex items-center justify-between px-5 py-3 border-b border-border">
                                    <h3 id="cancel-modal-title" class="text-lg font-semibold text-ink">Cancel Stock Transfer</h3>
                                    <button @click="showCancelModal = false" class="text-ink-muted hover:text-ink p-1" aria-label="Close">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <form action="{{ route('stock-transfers.cancel', $stockTransfer->id) }}" method="POST">
                                    @csrf
                                    <div class="p-6 space-y-4">
                                        <p class="text-sm text-ink-muted">
                                            Are you sure you want to cancel this stock transfer? This action cannot be undone.
                                        </p>
                                        <div>
                                            <label for="cancel-reason" class="block text-sm font-medium text-ink mb-1">Reason</label>
                                            <textarea id="cancel-reason" name="reason" rows="3" required maxlength="500"
                                                class="w-full rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary"
                                                placeholder="Why is this transfer being cancelled?">{{ old('reason') }}</textarea>
                                            @error('reason')
                                                <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-border">
                                        <x-button type="button" @click="showCancelModal = false" variant="secondary">Back</x-button>
                                        <x-button type="submit" variant="danger">Confirm Cancel</x-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                @endif

                @if(in_array($stockTransfer->status->value, ['Requested', 'BranchManagerApproved', 'HqApproved', 'InTransit']))
                    @can('reject', $stockTransfer)
                    <div x-data="{ showRejectModal: false }" class="inline">
                        <x-button @click="showRejectModal = true" variant="danger">Reject</x-button>

                        <div x-show="showRejectModal"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @keydown.escape.window="showRejectModal = false"
                             @click="showRejectModal = false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                             role="dialog"
                             aria-modal="true"
                             aria-labelledby="reject-modal-title">
                            <div class="bg-surface rounded-xl shadow-lg max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                                <div class="flex items-center justify-between px-5 py-3 border-b border-border">
                                    <h3 id="reject-modal-title" class="text-lg font-semibold text-ink">Reject Stock Transfer</h3>
                                    <button @click="showRejectModal = false" class="text-ink-muted hover:text-ink p-1" aria-label="Close">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <form action="{{ route('stock-transfers.reject', $stockTransfer->id) }}" method="POST">
                                    @csrf
                                    <div class="p-6 space-y-4">
                                        <p class="text-sm text-ink-muted">
                                            Are you sure you want to reject this stock transfer? This action cannot be undone.
                                        </p>
                                        <div>
                                            <label for="reject-reason" class="block text-sm font-medium text-ink mb-1">Reason</label>
                                            <textarea id="reject-reason" name="reason" rows="3" required maxlength="500"
                                                class="w-full rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary"
                                                placeholder="Why is this transfer being rejected?"></textarea>
                                            @error('reason')
                                                <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-border">
                                        <x-button type="button" @click="showRejectModal = false" variant="secondary">Back</x-button>
                                        <x-button type="submit" variant="danger">Confirm Reject</x-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                @endif

                @if(! $stockTransfer->canApproveBranchManager()
                    && ! $stockTransfer->canApproveHq()
                    && ! $stockTransfer->canDispatch()
                    && ! $stockTransfer->canReceive()
                    && ! $stockTransfer->canComplete()
                    && ! $stockTransfer->canCancel())
                    <p class="text-sm text-ink-muted">No actions available for this transfer.</p>
                @endif
            </div>

            @if($stockTransfer->canReceive())
                @can('receive', $stockTransfer)
                <form action="{{ route('stock-transfers.receive', $stockTransfer->id) }}" method="POST" class="mt-6 pt-6 border-t border-border space-y-4">
                    @csrf
                    <div>
                        <h4 class="text-sm font-semibold text-ink mb-2">Receive Items</h4>
                        <p class="text-sm text-ink-muted mb-3">Enter the quantities received for each item.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Expected</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Quantity Received</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockTransfer->items as $item)
                                    <tr class="border-t border-border">
                                        <td class="px-4 py-3 text-sm font-mono">{{ $item->currency_code }}</td>
                                        <td class="px-4 py-3 text-sm">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3">
                                            <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
                                            <input type="number" name="items[{{ $item->id }}][quantity_received]"
                                                min="0" step="any" required
                                                value="{{ old('items.'.$item->id.'.quantity_received', $item->quantity_received ?? '0') }}"
                                                class="w-32 rounded-md border-border bg-surface text-ink text-sm focus:border-primary focus:ring-primary"
                                                aria-label="Quantity received for {{ $item->currency_code }}">
                                            @error('items.'.$item->id.'.quantity_received')
                                                <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <x-button type="submit" variant="primary">Submit Received Quantities</x-button>
                    </div>
                </form>
                @endcan
            @endif
        </x-card>

        <x-card title="History">
            <ol class="space-y-4">
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $stockTransfer->requested_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Requested</p>
                        <p class="text-ink-muted">
                            {{ $stockTransfer->requested_by ? 'by '.($stockTransfer->requestedBy?->username ?? 'user #'.$stockTransfer->requested_by) : '' }}
                            {{ $stockTransfer->requested_at?->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $stockTransfer->branch_manager_approved_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Destination Branch Approved</p>
                        <p class="text-ink-muted">
                            {{ $stockTransfer->branch_manager_approved_by ? 'by '.($stockTransfer->branchManagerApprovedBy?->username ?? 'user #'.$stockTransfer->branch_manager_approved_by) : '' }}
                            {{ $stockTransfer->branch_manager_approved_at?->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </li>
                @if($stockTransfer->hq_approved_at)
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 bg-success"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">HQ Approved (legacy)</p>
                        <p class="text-ink-muted">
                            {{ $stockTransfer->hq_approved_by ? 'by '.($stockTransfer->hqApprovedBy?->username ?? 'user #'.$stockTransfer->hq_approved_by) : '' }}
                            {{ $stockTransfer->hq_approved_at?->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </li>
                @endif
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $stockTransfer->dispatched_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Dispatched</p>
                        <p class="text-ink-muted">{{ $stockTransfer->dispatched_at?->format('Y-m-d H:i') }}</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $stockTransfer->completed_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Completed</p>
                        <p class="text-ink-muted">{{ $stockTransfer->completed_at?->format('Y-m-d H:i') }}</p>
                    </div>
                </li>
            </ol>
        </x-card>
    </div>
</x-app-layout>
