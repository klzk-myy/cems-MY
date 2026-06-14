<x-app-layout title="Create Stock Transfer">
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-ink">Create Stock Transfer</h1>
            <p class="mt-1 text-sm text-ink-muted">Request a new stock transfer between branches</p>
        </div>

        <!-- Form Card -->
        <div class="bg-surface border border-border rounded-xl p-6">
            <form action="{{ route('stock-transfers.store') }}" method="POST">
                @csrf

                <x-select name="source_branch_id" label="Source Branch" :options="$branches ?? []" required placeholder="Select Source Branch" />
                <x-select name="destination_branch_id" label="Destination Branch" :options="$branches ?? []" required placeholder="Select Destination Branch" />
                <x-select name="currency_id" label="Currency" :options="$currencies ?? []" required placeholder="Select Currency" />

                <div class="mb-6">
                    <label for="amount" class="block text-sm font-medium text-ink-muted mb-2">Amount</label>
                    <div class="relative">
                        <x-input type="text" name="amount" id="amount" placeholder="0.00" required class="w-full" />
                    </div>
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-ink-muted mb-2">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Add any additional notes or instructions..." class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900 resize-none"></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <x-button href="{{ route('stock-transfers.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Transfer</x-button>
                </div>
            </form>
        </div>
</x-app-layout>
