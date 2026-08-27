<x-app-layout title="New Transaction">
    <x-page-header title="New Transaction" description="Create a new exchange transaction" />

    <x-card>
        <form method="POST" action="{{ route('transactions.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="customer_name" label="Customer Name" :required="true" />
                <x-select name="transaction_type" label="Type" :options="['buy' => 'Buy', 'sell' => 'Sell']" :required="true" />
                <x-input name="currency" label="Currency" :required="true" />
                <x-input name="amount" label="Amount" type="number" :required="true" />
            </div>
            <x-textarea name="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('transactions.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Create Transaction</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
