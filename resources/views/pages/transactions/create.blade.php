<x-app-layout title="New Transaction">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">New Transaction</h1>

        <form method="POST" action="{{ route('transactions.store') }}" class="bg-white rounded-lg shadow p-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                    <select name="type" class="w-full border rounded px-3 py-2" required>
                        <option value="Buy">Buy</option>
                        <option value="Sell">Sell</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full border rounded px-3 py-2" required>
                        @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                    <select name="currency_code" class="w-full border rounded px-3 py-2" required>
                        @foreach($currencies ?? [] as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Foreign Amount</label>
                    <input type="number" step="0.01" name="amount_foreign" class="w-full border rounded px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Exchange Rate</label>
                    <input type="number" step="0.0001" name="rate_used" class="w-full border rounded px-3 py-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Counter</label>
                    <select name="counter_id" class="w-full border rounded px-3 py-2" required>
                        @foreach($counters ?? [] as $counter)
                            <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Create Transaction</button>
                <a href="{{ route('transactions.index') }}" class="px-6 py-2 border rounded hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>