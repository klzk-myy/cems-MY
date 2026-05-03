<x-app-layout title="Export Reconciliation">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Export Reconciliation</h1>
                <p class="mt-1 text-sm text-gray-500">Export reconciliation report for bank submission</p>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Export Options</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="maybank">Maybank Current Account</option>
                        <option value="cimb">CIMB Business Account</option>
                        <option value="rhb">RHB Trading Account</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reconciliation Date</label>
                    <input type="date" value="2026-05-01" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="pdf">PDF Document</option>
                        <option value="excel">Excel Spreadsheet</option>
                        <option value="csv">CSV File</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="en">English</option>
                        <option value="ms">Bahasa Malaysia</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900">Preview</h3>
            </div>
            <div class="p-6 space-y-6">
                <!-- Header -->
                <div class="text-center border-b border-[#e5e5e5] pb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Bank Reconciliation Statement</h2>
                    <p class="text-sm text-gray-500">Maybank Current Account - May 2026</p>
                </div>

                <!-- Summary -->
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Bank Statement Balance</h4>
                        <p class="text-2xl font-semibold text-gray-900">RM 1,250,430.00</p>
                        <p class="text-sm text-gray-500">As of May 1, 2026</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Book Balance</h4>
                        <p class="text-2xl font-semibold text-gray-900">RM 1,248,920.50</p>
                        <p class="text-sm text-gray-500">As of May 1, 2026</p>
                    </div>
                </div>

                <!-- Adjustments -->
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Adjustments</h4>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (RM)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e5e5]">
                            <tr>
                                <td class="px-4 py-2 text-sm">Outstanding Checks (3 items)</td>
                                <td class="px-4 py-2 text-sm text-right">-25,750.00</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm">Deposits in Transit (2 items)</td>
                                <td class="px-4 py-2 text-sm text-right">77,500.00</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm">Bank Fee (not in books)</td>
                                <td class="px-4 py-2 text-sm text-right">-50.00</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm">Interest Earned (not in books)</td>
                                <td class="px-4 py-2 text-sm text-right">125.00</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium">Total Adjustments</td>
                                <td class="px-4 py-2 text-sm text-right font-medium">51,825.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Reconciled Balance -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-800">Reconciled Balance</p>
                            <p class="text-sm text-green-600">Bank = Book after adjustments</p>
                        </div>
                        <p class="text-2xl font-semibold text-green-700">RM 1,248,920.50</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('accounting.reconciliation') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Cancel</a>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">
                Download PDF
            </button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                Export
            </button>
        </div>
    </div>
</x-layouts.app>
