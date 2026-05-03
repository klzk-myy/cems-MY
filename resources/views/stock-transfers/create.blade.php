<x-app-layout title="Create Stock Transfer">
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-gray-900">Create Stock Transfer</h1>
            <p class="mt-1 text-sm text-gray-500">Request a new stock transfer between branches</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <form action="{{ route('stock-transfers.store') }}" method="POST">
                @csrf

                <!-- Source Branch -->
                <div class="mb-6">
                    <label for="source_branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Source Branch
                    </label>
                    <select name="source_branch_id" id="source_branch_id" required
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <option value="">Select Source Branch</option>
                        @forelse($branches ?? [] as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->code }} - {{ $branch->name }}</option>
                        @empty
                            <option value="" disabled>No branches available</option>
                        @endforelse
                    </select>
                    @error('source_branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Destination Branch -->
                <div class="mb-6">
                    <label for="destination_branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Destination Branch
                    </label>
                    <select name="destination_branch_id" id="destination_branch_id" required
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <option value="">Select Destination Branch</option>
                        @forelse($branches ?? [] as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->code }} - {{ $branch->name }}</option>
                        @empty
                            <option value="" disabled>No branches available</option>
                        @endforelse
                    </select>
                    @error('destination_branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Currency -->
                <div class="mb-6">
                    <label for="currency_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Currency
                    </label>
                    <select name="currency_id" id="currency_id" required
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <option value="">Select Currency</option>
                        @forelse($currencies ?? [] as $currency)
                            <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->name }}</option>
                        @empty
                            <option value="" disabled>No currencies available</option>
                        @endforelse
                    </select>
                    @error('currency_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div class="mb-6">
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Amount
                    </label>
                    <div class="relative">
                        <input type="text" name="amount" id="amount" required
                               placeholder="0.00"
                               class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
                               oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
                    </div>
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              placeholder="Add any additional notes or instructions..."
                              class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900 resize-none"></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#e5e5e5]">
                    <a href="{{ route('stock-transfers.index') }}"
                       class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Create Transfer
                    </button>
                </div>
            </form>
        </div>
</x-app-layout>
