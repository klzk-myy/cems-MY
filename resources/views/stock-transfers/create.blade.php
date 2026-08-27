<x-app-layout title="New Stock Transfer">
    <x-page-header title="New Stock Transfer" description="Create a new inter-branch transfer" />

    <x-card>
        <form method="POST" action="{{ route('stock-transfers.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-select name="from_branch" label="From Branch" :options="['branch1' => 'Branch 1', 'branch2' => 'Branch 2']" :required="true" />
                <x-select name="to_branch" label="To Branch" :options="['branch1' => 'Branch 1', 'branch2' => 'Branch 2']" :required="true" />
                <x-select name="currency" label="Currency" :options="['USD' => 'USD', 'EUR' => 'EUR']" :required="true" />
                <x-input name="amount" label="Amount" type="number" :required="true" />
            </div>
            <x-textarea name="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('stock-transfers.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Create Transfer</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
