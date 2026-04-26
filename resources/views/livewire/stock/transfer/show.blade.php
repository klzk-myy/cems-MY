<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button wire:click="back" class="btn btn-ghost btn-icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold text-gray-900">
                            Transfer #{{ $transferData['transfer_number'] ?? $stockTransferId }}
                        </h1>
                        <span class="badge {{ $transferData['status_badge_class'] ?? 'badge-default' }}">
                            {{ $transferData['status_label'] ?? 'Unknown' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">
                        Created {{ $transferData['requested_at'] ?? 'N/A' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if($canCancel)
                    <button wire:click="openCancelModal" class="btn btn-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel Transfer
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Transfer Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transfer Information</h3>
            </div>
            <div class="card-body">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-gray-500">Transfer Date</dt>
                        <dd class="font-mono">{{ $transferData['transfer_date'] ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Transfer Type</dt>
                        <dd>{{ $transferData['type'] ?? 'Standard' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Source Branch</dt>
                        <dd class="font-medium">{{ $transferData['source_branch_name'] ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Destination Branch</dt>
                        <dd class="font-medium">{{ $transferData['destination_branch_name'] ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Total Value</dt>
                        <dd class="text-xl font-mono font-bold text-amber-500">
                            RM {{ number_format((float) ($transferData['total_value'] ?? 0), 2) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Approval Timeline</h3>
            </div>
            <div class="card-body">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-gray-500">Requested By</dt>
                        <dd class="font-medium">{{ $transferData['requested_by'] ?? 'N/A' }}</dd>
                        <dd class="text-sm text-gray-500">{{ $transferData['requested_at'] ?? '' }}</dd>
                    </div>
                    @if($transferData['branch_manager_approved_by'])
                    <div>
                        <dt class="text-sm text-gray-500">Branch Manager Approved</dt>
                        <dd class="font-medium text-green-600">{{ $transferData['branch_manager_approved_by'] }}</dd>
                        <dd class="text-sm text-gray-500">{{ $transferData['branch_manager_approved_at'] ?? '' }}</dd>
                    </div>
                    @endif
                    @if($transferData['hq_approved_by'])
                    <div>
                        <dt class="text-sm text-gray-500">HQ Approved</dt>
                        <dd class="font-medium text-green-600">{{ $transferData['hq_approved_by'] }}</dd>
                        <dd class="text-sm text-gray-500">{{ $transferData['hq_approved_at'] ?? '' }}</dd>
                    </div>
                    @endif
                    @if($transferData['dispatched_at'])
                    <div>
                        <dt class="text-sm text-gray-500">Dispatched</dt>
                        <dd class="text-sm text-gray-500">{{ $transferData['dispatched_at'] }}</dd>
                    </div>
                    @endif
                    @if($transferData['completed_at'])
                    <div>
                        <dt class="text-sm text-gray-500">Completed</dt>
                        <dd class="text-sm text-gray-500">{{ $transferData['completed_at'] }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Actions</h3>
            </div>
            <div class="card-body space-y-3">
                @if($canApproveBm)
                    <button wire:click="approveByBranchManager" class="btn btn-success w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Approve (Branch Manager)
                    </button>
                @endif

                @if($canApproveHq)
                    <button wire:click="approveByHQ" class="btn btn-success w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Approve (HQ)
                    </button>
                @endif

                @if($canDispatch)
                    <button wire:click="dispatchTransfer" class="btn btn-primary w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        Dispatch Transfer
                    </button>
                @endif

                @if($canReceive)
                    <button wire:click="openReceiveModal" class="btn btn-info w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Receive Items
                    </button>
                @endif

                @if($canComplete)
                    <button wire:click="complete" class="btn btn-success w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Complete Transfer
                    </button>
                @endif

                @if(!$canApproveBm && !$canApproveHq && !$canDispatch && !$canReceive && !$canComplete && !$canCancel)
                    <p class="text-gray-500 text-sm text-center py-4">No actions available</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Transfer Items --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Transfer Items</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th class="text-right">Quantity Sent</th>
                        <th class="text-right">Rate (MYR)</th>
                        <th class="text-right">Value (MYR)</th>
                        <th class="text-right">Quantity Received</th>
                        <th class="text-right">Variance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-xs">
                                    {{ substr($item['currency_code'], 0, 1) }}
                                </div>
                                <span class="font-mono font-medium">{{ $item['currency_code'] }}</span>
                            </div>
                        </td>
                        <td class="font-mono text-right">{{ number_format((float) $item['quantity'], 2) }}</td>
                        <td class="font-mono text-right">{{ number_format((float) $item['rate'], 4) }}</td>
                        <td class="font-mono text-right">RM {{ number_format((float) $item['value_myr'], 2) }}</td>
                        <td class="font-mono text-right">{{ number_format((float) ($item['quantity_received'] ?? 0), 2) }}</td>
                        <td class="text-right">
                            @if($item['has_variance'])
                                <span class="text-red-500 font-mono">
                                    {{ number_format((float) $item['variance'], 2) }}
                                </span>
                            @else
                                <span class="text-gray-500 font-mono">-</span>
                            @endif
                        </td>
                        <td>
                            @if($item['is_fully_received'])
                                <span class="badge badge-success">Fully Received</span>
                            @elseif(bccomp($item['quantity_received'] ?? '0', '0', 4) > 0)
                                <span class="badge badge-warning">Partially Received</span>
                            @else
                                <span class="badge badge-default">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">No items in this transfer</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-medium">Total</td>
                        <td class="font-mono text-right font-bold">RM {{ number_format((float) $totalValue, 2) }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Notes --}}
    @if($transferData['notes'])
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Notes</h3>
        </div>
        <div class="card-body">
            <p class="text-gray-500">{{ $transferData['notes'] }}</p>
        </div>
    </div>
    @endif

    {{-- Cancellation Reason --}}
    @if($transferData['cancellation_reason'])
    <div class="card mt-6 border-red-200">
        <div class="card-header bg-red-50">
            <h3 class="card-title text-red-700">Cancellation Reason</h3>
        </div>
        <div class="card-body">
            <p class="text-red-600">{{ $transferData['cancellation_reason'] }}</p>
        </div>
    </div>
    @endif

    {{-- Cancel Modal --}}
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show" x-on:keydown.escape.window="show = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-25"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Cancel Transfer</h3>
                <p class="text-gray-500 mb-4">Are you sure you want to cancel this transfer? This action cannot be undone.</p>
                <div class="form-group mb-4">
                    <label class="form-label">Reason for Cancellation</label>
                    <textarea
                        wire:model.live="cancelReason"
                        class="form-input"
                        rows="3"
                        placeholder="Enter the reason for cancellation..."
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeCancelModal" class="btn btn-secondary">Close</button>
                    <button wire:click="cancel" class="btn btn-danger">Cancel Transfer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Receive Modal --}}
    @if($showReceiveModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show" x-on:keydown.escape.window="show = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-25"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Receive Items</h3>
                <p class="text-gray-500 mb-4">Enter the quantity received for each item.</p>

                <div class="space-y-4 mb-6">
                    @foreach($items as $item)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center font-bold text-xs">
                                {{ substr($item['currency_code'], 0, 1) }}
                            </div>
                            <span class="font-mono font-medium">{{ $item['currency_code'] }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">of {{ number_format((float) $item['quantity'], 2) }}</span>
                            <input
                                type="number"
                                wire:model.live="receiveQuantities.{{ $item['id'] }}"
                                step="0.01"
                                min="0"
                                max="{{ $item['quantity'] }}"
                                class="form-input w-32 text-right font-mono"
                            >
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="closeReceiveModal" class="btn btn-secondary">Close</button>
                    <button wire:click="receive" class="btn btn-primary">Confirm Receipt</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
