<x-app-layout title="Batch Upload">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Batch Upload"
            description="Upload multiple transactions in bulk"
        />

        <x-card title="Upload Instructions">
            <div class="space-y-3">
                <p class="text-sm text-ink-muted">1. Prepare your CSV file with transaction data</p>
                <p class="text-sm text-ink-muted">2. Ensure the file follows the required format</p>
                <p class="text-sm text-ink-muted">3. Maximum file size: 5MB</p>
                <p class="text-sm text-ink-muted">4. Supported format: CSV</p>
            </div>
            <div class="mt-4 p-4 bg-canvas-subtle rounded-lg">
                <p class="text-sm font-medium text-ink-muted mb-2">Required Columns:</p>
                <p class="text-xs text-ink-muted">type, amount, currency, customer_id, rate, counter_id</p>
            </div>
        </x-card>

        <x-card title="Upload File">
            <form method="POST" action="{{ route('transactions.batch-upload.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-6">
                    <x-input
                        type="file"
                        id="file"
                        name="file"
                        accept=".csv"
                        label="Transaction File"
                        inline
                    />
                    @error('file')
                        <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <x-select
                        id="branch_id"
                        name="branch_id"
                        label="Branch"
                        :options="['' => 'Select Branch'] + collect($branches ?? [])->pluck('name', 'id')->toArray()"
                        inline
                    />
                    @error('branch_id')
                        <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-4">
                    <x-button type="submit" variant="primary">Upload Transactions</x-button>
                    <x-button variant="secondary" href="{{ route('transactions.index') }}">Cancel</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="Template">
            <p class="text-sm text-ink-muted mb-4">Download the template file to help format your data correctly.</p>
            <x-button variant="secondary" href="{{ route('transactions.batch-upload.template') }}">
                Download CSV Template
            </x-button>
        </x-card>
    </div>
</x-app-layout>
