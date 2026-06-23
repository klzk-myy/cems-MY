<x-app-layout title="New Transaction">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">New Transaction</h1>

        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-select name="type" label="Transaction Type" :options="['Buy' => 'Buy', 'Sell' => 'Sell']" required />

                <x-select name="customer_id" label="Customer" :options="$customers ?? []" required />

                <x-select name="currency_code" label="Currency" :options="$currencies ?? []" required />

                <x-input type="number" name="amount_foreign" label="Foreign Amount" step="0.01" required />

                <x-input type="number" name="rate_used" label="Exchange Rate" step="0.0001" required />

                <x-select name="counter_id" label="Counter" :options="$counters ?? []" required />
            </div>

            <div class="mt-6 flex gap-4">
                <x-button type="submit" variant="primary">Create Transaction</x-button>
                <x-button href="{{ route('transactions.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </div>
</x-app-layout>