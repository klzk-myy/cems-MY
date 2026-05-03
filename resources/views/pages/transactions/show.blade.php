<x-app-layout title="Transaction Details">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Transaction #{{ $transaction->reference }}</h1>
            <div class="flex gap-2">
                @if($transaction->status === 'Pending' && auth()->user()->isManager())
                    <form method="POST" action="{{ route('transactions.approve', $transaction) }}">
                        @csrf
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Approve</button>
                    </form>
                    <a href="{{ route('transactions.cancel.show', $transaction) }}" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Cancel</a>
                @endif
                <a href="{{ route('transactions.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Back</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Transaction Details</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Reference</dt>
                        <dd class="font-mono">{{ $transaction->reference }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Type</dt>
                        <dd>
                            <span class="px-2 py-1 rounded text-xs {{ $transaction->type === 'Buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $transaction->type }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Status</dt>
                        <dd>{{ $transaction->status }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Date</dt>
                        <dd>{{ $transaction->created_at->format('M d, Y H:i:s') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Amounts</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Foreign Amount</dt>
                        <dd class="font-bold">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Exchange Rate</dt>
                        <dd>{{ number_format($transaction->rate_used, 4) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Local Amount</dt>
                        <dd class="font-bold">{{ number_format($transaction->amount_local, 2) }} MYR</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Customer</h2>
                @if($transaction->customer)
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Name</dt>
                        <dd>{{ $transaction->customer->full_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ID Type</dt>
                        <dd>{{ $transaction->customer->id_type }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ID Number</dt>
                        <dd>{{ $transaction->customer->id_number }}</dd>
                    </div>
                </dl>
                @else
                <p class="text-gray-500">No customer assigned</p>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Branch & User</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Branch</dt>
                        <dd>{{ $transaction->branch->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Processed By</dt>
                        <dd>{{ $transaction->user->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Counter</dt>
                        <dd>{{ $transaction->counter->name ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>