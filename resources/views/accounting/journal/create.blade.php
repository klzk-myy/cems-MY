<x-app-layout title="Create Journal Entry">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Create Journal Entry</h1>
                <p class="mt-1 text-sm text-gray-500">Create a new double-entry journal entry</p>
            </div>
            <a href="{{ route('accounting.journal.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">
                Back
            </a>
        </div>

        <!-- Form -->
        <form action="{{ route('accounting.journal.store') }}" method="POST" class="space-y-6">
            @csrf
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 space-y-6">
                <!-- Entry Details -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Entry Date</label>
                        <input type="date" name="date" value="{{ date('Y-m-d') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                        <input type="text" name="reference" placeholder="JE-0001" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" placeholder="Enter journal entry description" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                </div>
            </div>

            <!-- Journal Lines -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-[#e5e5e5]">
                    <h3 class="text-sm font-medium text-gray-900">Journal Lines</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="journal-lines" class="divide-y divide-[#e5e5e5]">
                        <tr>
                            <td class="px-4 py-3">
                                <select name="lines[0][account]" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                    <option value="">Select Account</option>
                                    <option value="1100-001">1100-001 - Cash MYR</option>
                                    <option value="1100-002">1100-002 - Cash USD</option>
                                    <option value="2100-001">2100-001 - Accounts Payable</option>
                                    <option value="5100-001">5100-001 - Revenue</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" name="lines[0][description]" placeholder="Line description" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="lines[0][debit]" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg text-right">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="lines[0][credit]" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg text-right">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" class="text-red-600 hover:text-red-800">Remove</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3">
                                <select name="lines[1][account]" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                    <option value="">Select Account</option>
                                    <option value="1100-001">1100-001 - Cash MYR</option>
                                    <option value="1100-002">1100-002 - Cash USD</option>
                                    <option value="2100-001">2100-001 - Accounts Payable</option>
                                    <option value="5100-001">5100-001 - Revenue</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" name="lines[1][description]" placeholder="Line description" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="lines[1][debit]" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg text-right">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="lines[1][credit]" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg text-right">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" class="text-red-600 hover:text-red-800">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-[#e5e5e5]">
                    <button type="button" id="add-line" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">+ Add Line</button>
                </div>
            </div>

            <!-- Totals -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <div class="flex justify-end gap-8">
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total Debit</p>
                        <p class="text-lg font-semibold" id="total-debit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Total Credit</p>
                        <p class="text-lg font-semibold" id="total-credit">0.00</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Difference</p>
                        <p class="text-lg font-semibold" id="difference">0.00</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <button type="button" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Create Entry</button>
            </div>
        </form>
    </div>
</x-app-layout>
