<x-app-layout title="Stock Transfer #{{ $stockTransfer->id }}">
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Stock Transfer #{{ $stockTransfer->id }}</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    {{ $stockTransfer->sourceBranch->code ?? 'N/A' }} &rarr; {{ $stockTransfer->destinationBranch->code ?? 'N/A' }}
                </p>
            </div>
            <a href="{{ route('stock-transfers.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-canvas-subtle">
                Back
            </a>
        </div>

        <!-- Transfer Details Card -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-ink mb-4">Transfer Details</h2>
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
                <div class="mt-6 pt-6 border-t border-[#e5e5e5]">
                    <dt class="text-sm font-medium text-ink-muted mb-2">Notes</dt>
                    <dd class="text-sm text-gray-700">{{ $stockTransfer->notes }}</dd>
                </div>
            @endif
        </div>

        <!-- Approval Statuses -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Branch Manager Approval -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-md font-medium text-ink mb-4">Branch Manager Approval</h3>
                <div class="space-y-3">
                    @if($stockTransfer->branchManagerApprovedBy)
                        <div class="flex items-center gap-2 text-green-700">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium">Approved</span>
                        </div>
                        <p class="text-sm text-ink-muted">By: {{ $stockTransfer->branchManagerApprovedBy->name ?? 'N/A' }}</p>
                        <p class="text-sm text-ink-muted">
                            {{ $stockTransfer->branch_manager_approved_at?->format('d M Y H:i:s') ?? 'N/A' }}
                        </p>
                    @else
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Pending</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- HQ Approval -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h3 class="text-md font-medium text-ink mb-4">HQ Approval</h3>
                <div class="space-y-3">
                    @if($stockTransfer->hqApproval)
                        <div class="flex items-center gap-2 text-green-700">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium">Approved</span>
                        </div>
                        <p class="text-sm text-ink-muted">By: {{ $stockTransfer->hqApproval->name ?? 'N/A' }}</p>
                        <p class="text-sm text-ink-muted">
                            {{ $stockTransfer->hq_approved_at?->format('d M Y H:i:s') ?? 'N/A' }}
                        </p>
                    @else
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm">Pending</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Transfer Items -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h2 class="text-lg font-medium text-ink mb-4">Transfer Items</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#e5e5e5]">
                    <thead>
                        <tr class="bg-canvas-subtle">
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
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
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Approve BM Button -->
                @if($stockTransfer->canApproveBranchManager())
                    <form action="{{ route('stock-transfers.approve-bm', $stockTransfer->id) }}" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" variant="info">Approve (Branch Manager)</x-button>
                    </form>
                @endif

                <!-- Approve HQ Button -->
                @if($stockTransfer->canApproveHq())
                    <form action="{{ route('stock-transfers.approve-hq', $stockTransfer->id) }}" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" variant="indigo">Approve (HQ)</x-button>
                    </form>
                @endif

                <!-- Dispatch Button -->
                @if($stockTransfer->canDispatch())
                    <form action="{{ route('stock-transfers.dispatch', $stockTransfer->id) }}" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" variant="purple">Dispatch</x-button>
                    </form>
                @endif

                <!-- Receive Button -->
                @if($stockTransfer->canReceive())
                    <form action="{{ route('stock-transfers.receive', $stockTransfer->id) }}" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" variant="teal">Receive</x-button>
                    </form>
                @endif

                <!-- Complete Button -->
                @if($stockTransfer->canComplete())
                    <form action="{{ route('stock-transfers.complete', $stockTransfer->id) }}" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" variant="success">Complete</x-button>
                    </form>
                @endif

                <!-- Cancel Button -->
                @if($stockTransfer->canCancel())
                    <form action="{{ route('stock-transfers.cancel', $stockTransfer->id) }}" method="POST" class="inline"
                          onsubmit="return confirm('Are you sure you want to cancel this stock transfer?');">
                        @csrf
                        <x-button type="submit" variant="danger">Cancel</x-button>
                    </form>
                @endif
            </div>
        </div>
</x-app-layout>
