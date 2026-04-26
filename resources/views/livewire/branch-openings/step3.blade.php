<div>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Step 3: Opening Balance</h1>
            <div class="text-sm text-gray-600">Step 3 of 3</div>
        </div>

        <p class="text-gray-600 mb-6">
            Create the opening balance journal entry for the branch. This represents the initial capital contribution.
        </p>

        <form wire:submit="processStep3">
            <div class="space-y-4 mb-8">
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700">Opening Balance Amount (MYR) *</label>
                    <input type="number" wire:model="amount" id="amount"
                           step="0.01" min="0.01" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Total from currency pools: {{ $totalPoolAmount }}</p>
                </div>

                <div>
                    <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
                    <input type="text" wire:model="reference" id="reference"
                           placeholder="Opening balance for {{ $branch->code }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold mb-2">Journal Entry Preview</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Debit:</span>
                            <span class="font-medium">1010 - CASH MYR</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Credit:</span>
                            <span class="font-medium">3000 - EQUITY</span>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <p class="text-xs text-gray-500">
                                This entry will be automatically submitted and approved as it represents initial capital.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($error)
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-600">{{ $error }}</p>
                </div>
            @endif

            <div class="mt-8 flex items-center justify-between">
                <a href="{{ route('branches.open.step2', ['branch' => $branch->id]) }}" class="text-gray-600 hover:text-gray-800">
                    Back to Step 2
                </a>
                <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition">
                    Complete Branch Opening
                </button>
            </div>
        </form>
    </div>
</div>
