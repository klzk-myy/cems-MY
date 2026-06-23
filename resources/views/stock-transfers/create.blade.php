<x-app-layout title="Create Stock Transfer">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Create Stock Transfer"
            description="Request a new stock transfer between branches"
        />

        <x-card class="p-6">
            <form action="{{ route('stock-transfers.store') }}" method="POST">
                @csrf

                <x-select name="source_branch_id" label="Source Branch" :options="$branches ?? []" required placeholder="Select Source Branch" />
                <x-select name="destination_branch_id" label="Destination Branch" :options="$branches ?? []" required placeholder="Select Destination Branch" />
                <x-select name="currency_id" label="Currency" :options="$currencies ?? []" required placeholder="Select Currency" />

                <x-input type="text" name="amount" id="amount" label="Amount" placeholder="0.00" required />

                <x-textarea
                    name="notes"
                    label="Notes (Optional)"
                    rows="3"
                    placeholder="Add any additional notes or instructions..."
                >{{ old('notes') }}</x-textarea>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <x-button href="{{ route('stock-transfers.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Transfer</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
