<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Stock Transfers</h1>
        <p class="text-sm text-gray-500">Inter-branch currency transfers</p>
    </div>

    {{-- Status Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <button
            wire:click="$set('statusFilter', '')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === '' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['all'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === '' ? 'text-white/80' : '' }}">All</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'Requested')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'Requested' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['requested'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'Requested' ? 'text-white/80' : '' }}">Pending</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'BranchManagerApproved')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'BranchManagerApproved' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['bm_approved'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'BranchManagerApproved' ? 'text-white/80' : '' }}">BM Approved</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'HQApproved')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'HQApproved' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['hq_approved'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'HQApproved' ? 'text-white/80' : '' }}">HQ Approved</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'InTransit')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'InTransit' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['in_transit'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'InTransit' ? 'text-white/80' : '' }}">In Transit</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'Completed')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'Completed' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['completed'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'Completed' ? 'text-white/80' : '' }}">Completed</p>
        </button>
        <button
            wire:click="$set('statusFilter', 'Cancelled')"
            class="p-4 rounded-lg border transition-all {{ $statusFilter === 'Cancelled' ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 border-gray-200 hover:border-amber-500' }}"
        >
            <p class="text-2xl font-mono font-bold">{{ $statusCounts['cancelled'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 {{ $statusFilter === 'Cancelled' ? 'text-white/80' : '' }}">Cancelled</p>
        </button>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Date From</label>
                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        class="form-input"
                    >
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Date To</label>
                    <input
                        type="date"
                        wire:model.live="dateTo"
                        class="form-input"
                    >
                </div>
                <div class="md:col-span-2 flex items-end gap-3">
                    <button
                        wire:click="clearFilters"
                        class="btn btn-ghost"
                    >
                        Clear
                    </button>
                    <a
                        href="{{ route('stock-transfers.create') }}"
                        class="btn btn-primary ml-auto"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Transfer
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Transfers Table --}}
    <div class="card">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>From Branch</th>
                        <th>To Branch</th>
                        <th>Type</th>
                        <th class="text-right">Value (MYR)</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $transfer)
                    <tr>
                        <td class="font-mono text-xs">#{{ $transfer['id'] }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                <span class="text-sm">{{ $transfer['source_branch_name'] ?? 'N/A' }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                                <span class="text-sm">{{ $transfer['destination_branch_name'] ?? 'N/A' }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-sm">{{ $transfer['type'] ?? 'Standard' }}</span>
                        </td>
                        <td class="font-mono text-right">{{ number_format((float) ($transfer['total_value_myr'] ?? 0), 2) }}</td>
                        <td>
                            <span class="badge {{ $this->getStatusBadgeClass($transfer['status'] ?? '') }}">
                                {{ $this->getStatusLabel($transfer['status'] ?? '') }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 bg-gray-100 rounded flex items-center justify-center text-xs">
                                    {{ substr($transfer['requested_by'] ?? '?', 0, 1) }}
                                </div>
                                <span class="text-sm">{{ $transfer['requested_by'] ?? 'N/A' }}</span>
                            </div>
                        </td>
                        <td class="text-gray-500">{{ \Carbon\Carbon::parse($transfer['created_at'])->format('d M Y') }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('stock-transfers.show', $transfer['id']) }}" class="btn btn-ghost btn-icon" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state py-12">
                                <div class="empty-state-icon">
                                    <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                </div>
                                <p class="empty-state-title">No transfers found</p>
                                <p class="empty-state-description">Create a stock transfer to move currency between branches</p>
                                <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary mt-4">New Transfer</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($totalCount > 25)
            <div class="card-footer">
                {{ $transfers->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
