<x-app-layout title="Create Stock Transfer">
    <div class="space-y-6">
        <x-page-header
            title="Create Stock Transfer"
            description="Request a new stock transfer between branches"
        />

        <x-card>
            <form action="{{ route('stock-transfers.store') }}" method="POST">
                @csrf

                <x-select name="source_branch_name" label="Source Branch" :options="$branches ?? []" required placeholder="Select Source Branch" />
                <x-select name="destination_branch_name" label="Destination Branch" :options="$branches ?? []" required placeholder="Select Destination Branch" />
                <x-select
                    name="type"
                    label="Transfer Type"
                    :options="['Standard' => 'Standard', 'Emergency' => 'Emergency', 'Scheduled' => 'Scheduled', 'Return' => 'Return']"
                    required
                    placeholder="Select Type"
                />
                <x-select name="items[0][currency_code]" label="Currency" :options="$currencies ?? []" required placeholder="Select Currency" />

                <div class="grid grid-cols-3 gap-4">
                    <x-input type="text" name="items[0][quantity]" label="Quantity" placeholder="0.00" required inputmode="decimal" />
                    <x-input type="text" name="items[0][rate]" label="Rate (MYR)" placeholder="0.0000" required inputmode="decimal" />
                    <x-input type="text" name="items[0][value_myr]" label="Value (MYR)" placeholder="0.00" required inputmode="decimal" />
                </div>

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
