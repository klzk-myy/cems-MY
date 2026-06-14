<x-app-layout title="Transaction Details">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Transaction Details</h1>
                <p class="text-sm text-ink-muted mt-1">ID: {{ $transaction['id'] ?? 'N/A' }}</p>
            </div>
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
                {{ ($transaction['status'] ?? '') === 'Completed' ? 'bg-green-100 text-green-700' : (($transaction['status'] ?? '') === 'Pending' ? 'bg-yellow-100 text-yellow-700' : (($transaction['status'] ?? '') === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-canvas-subtle text-gray-700')) }}">
                {{ $transaction['status'] ?? 'N/A' }}
            </span>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Transaction Information</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($transaction['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $transaction['type'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Foreign Currency</label>
                        <p class="text-sm text-ink">{{ $transaction['currency'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">FCY Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['fcy_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Exchange Rate</label>
                        <p class="text-sm text-ink">{{ $transaction['rate'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">MYR Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['myr_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Counter</label>
                        <p class="text-sm text-ink">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Branch</label>
                        <p class="text-sm text-ink">{{ $transaction['branch_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Created By</label>
                        <p class="text-sm text-ink">{{ $transaction['created_by'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Created At</label>
                        <p class="text-sm text-ink">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Customer Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Customer Name</label>
                        <p class="text-sm text-ink">{{ $transaction['customer_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Customer ID</label>
                        <p class="text-sm text-ink">{{ $transaction['customer_id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">ID Type</label>
                        <p class="text-sm text-ink">{{ $transaction['id_type'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">ID Number</label>
                        <p class="text-sm text-ink">{{ $transaction->customer->id_number_masked ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">CDD Level</label>
                        <p class="text-sm text-ink">{{ $transaction['cdd_level'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl">
            <div class="p-6">
                <div class="flex items-center gap-4 flex-wrap">
                    @if(($transaction['status'] ?? '') === 'Pending')
                        <form method="POST" action="{{ route('transactions.approve', $transaction['id'] ?? 0) }}" class="contents">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('transactions.reject', $transaction['id'] ?? 0) }}" class="contents">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                                Reject
                            </button>
                        </form>
                    @endif
                    @if(($transaction['status'] ?? '') === 'PendingCancellation')
                        <a href="{{ route('transactions.approve-cancellation', $transaction['id'] ?? 0) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">
                            Approve Cancellation
                        </a>
                        <a href="{{ route('transactions.reject-cancellation', $transaction['id'] ?? 0) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Reject Cancellation
                        </a>
                    @endif
                    @if(($transaction['status'] ?? '') === 'Completed')
                        <a href="{{ route('transactions.cancel', $transaction['id'] ?? 0) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-red-300 text-red-600 hover:bg-red-50">
                            Request Cancellation
                        </a>
                    @endif
                    <a href="{{ route('transactions.print', $transaction['id'] ?? 0) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                        Print Receipt
                    </a>
                    <a href="{{ route('transactions.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                        Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>