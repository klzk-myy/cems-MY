<x-app-layout title="Batch Upload">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Batch Upload</h1>
            <p class="text-sm text-ink-muted mt-1">Upload multiple transactions in bulk</p>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Upload Instructions</h2>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <p class="text-sm text-ink-muted">1. Prepare your CSV file with transaction data</p>
                    <p class="text-sm text-ink-muted">2. Ensure the file follows the required format</p>
                    <p class="text-sm text-ink-muted">3. Maximum file size: 5MB</p>
                    <p class="text-sm text-ink-muted">4. Supported format: CSV</p>
                </div>
                <div class="mt-4 p-4 bg-canvas-subtle rounded-lg">
                    <p class="text-sm font-medium text-gray-700 mb-2">Required Columns:</p>
                    <p class="text-xs text-ink-muted">type, amount, currency, customer_id, rate, counter_id</p>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Upload File</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('transactions.batch-upload.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-6">
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">Transaction File</label>
                        <input
                            type="file"
                            id="file"
                            name="file"
                            accept=".csv"
                            class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg cursor-pointer"
                        >
                        @error('file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">Branch</label>
                        <select
                            id="branch_id"
                            name="branch_id"
                            class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg"
                        >
                            <option value="">Select Branch</option>
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover"
                        >
                            Upload Transactions
                        </button>
                        <a
                            href="{{ route('transactions.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Template</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-ink-muted mb-4">Download the template file to help format your data correctly.</p>
                <a
                    href="{{ route('transactions.batch-upload.template') }}"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle"
                >
                    Download CSV Template
                </a>
            </div>
        </div>
    </div>
</x-app-layout>