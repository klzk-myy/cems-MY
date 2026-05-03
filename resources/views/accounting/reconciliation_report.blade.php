<x-app-layout title="Reconciliation Report">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Reconciliation Report</h1>
                <p class="mt-1 text-sm text-gray-500">Monthly reconciliation summary report</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">
                    Print
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Export PDF
                </button>
            </div>
        </div>

        <!-- Report Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Bank Accounts</option>
                    <option value="maybank">Maybank Current Account</option>
                    <option value="cimb">CIMB Business Account</option>
                    <option value="rhb">RHB Trading Account</option>
                </select>
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="2026-05">May 2026</option>
                    <option value="2026-04">April 2026</option>
                    <option value="2026-03">March 2026</option>
                </select>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Generate Report</button>
            </div>
        </div>

        <!-- Report Content -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <!-- Report Header -->
            <div class="p-6 border-b border-[#e5e5e5]">
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-gray-900">Bank Reconciliation Report</h2>
                    <p class="text-sm text-gray-500 mt-1">Maybank Current Account - May 2026</p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div>
                        <p class="text-sm text-gray-500">Report Date</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">2026-05-03</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Prepared By</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">Admin User</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Approved By</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">-</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="mt-1">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Balances Section -->
            <div class="p-6 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Section 1: Balance Comparison</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-4 border border-[#e5e5e5] rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-900">Bank Statement Balance</p>
                            <p class="text-lg font-semibold text-gray-900">RM 1,250,430.00</p>
                        </div>
                        <p class="text-xs text-gray-500">As of May 1, 2026</p>
                    </div>
                    <div class="p-4 border border-[#e5e5e5] rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-900">Book Balance</p>
                            <p class="text-lg font-semibold text-gray-900">RM 1,248,920.50</p>
                        </div>
                        <p class="text-xs text-gray-500">As of May 1, 2026</p>
                    </div>
                </div>
            </div>

            <!-- Adjustments Section -->
            <div class="p-6 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Section 2: Adjustments to Bank Balance</h3>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900">Outstanding Checks</td>
                            <td class="px-4 py-2"></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm pl-8">Check #CHK-2021</td>
                            <td class="px-4 py-2 text-sm">2026-04-28</td>
                            <td class="px-4 py-2 text-sm text-right">2026-04-28</td>
                            <td class="px-4 py-2 text-sm text-right">-5,000.00</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm pl-8">Check #CHK-2022</td>
                            <td class="px-4 py-2 text-sm">2026-04-30</td>
                            <td class="px-4 py-2 text-sm text-right">2026-04-30</td>
                            <td class="px-4 py-2 text-sm text-right">-12,500.00</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm pl-8">Check #CHK-2023</td>
                            <td class="px-4 py-2 text-sm">2026-05-02</td>
                            <td class="px-4 py-2 text-sm text-right">2026-05-02</td>
                            <td class="px-4 py-2 text-sm text-right">-8,250.00</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900">Total Outstanding Checks</td>
                            <td class="px-4 py-2 text-sm text-right font-medium">-25,750.00</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900 pt-4">Deposits in Transit</td>
                            <td class="px-4 py-2"></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm pl-8">Deposit #DEP-0892</td>
                            <td class="px-4 py-2 text-sm">2026-04-30</td>
                            <td class="px-4 py-2 text-sm text-right">2026-04-30</td>
                            <td class="px-4 py-2 text-sm text-right">45,000.00</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm pl-8">Deposit #DEP-0893</td>
                            <td class="px-4 py-2 text-sm">2026-05-01</td>
                            <td class="px-4 py-2 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-2 text-sm text-right">32,500.00</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900">Total Deposits in Transit</td>
                            <td class="px-4 py-2 text-sm text-right font-medium">77,500.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Book Adjustments Section -->
            <div class="p-6 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Section 3: Adjustments to Book Balance</h3>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        <tr>
                            <td class="px-4 py-2 text-sm">Bank Service Charge</td>
                            <td class="px-4 py-2 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-2 text-sm text-right">-50.00</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-sm">Interest Income</td>
                            <td class="px-4 py-2 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-2 text-sm text-right">125.00</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Total Book Adjustments</td>
                            <td class="px-4 py-2"></td>
                            <td class="px-4 py-2 text-sm text-right font-medium">75.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Final Reconciliation -->
            <div class="p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Section 4: Final Reconciliation</h3>
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-900">Bank Statement Balance</p>
                            <p class="text-sm font-medium text-gray-900">RM 1,250,430.00</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-900 pl-4">Less: Outstanding Checks</p>
                            <p class="text-sm font-medium text-gray-900">-RM 25,750.00</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-900 pl-4">Add: Deposits in Transit</p>
                            <p class="text-sm font-medium text-gray-900">RM 77,500.00</p>
                        </div>
                        <div class="flex items-center justify-between border-t border-green-300 pt-2">
                            <p class="text-sm font-semibold text-gray-900">Adjusted Bank Balance</p>
                            <p class="text-sm font-semibold text-gray-900">RM 1,302,180.00</p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-900">Book Balance</p>
                            <p class="text-sm font-medium text-gray-900">RM 1,248,920.50</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-900 pl-4">Add: Bank Adjustments</p>
                            <p class="text-sm font-medium text-gray-900">RM 75.00</p>
                        </div>
                        <div class="flex items-center justify-between border-t border-blue-300 pt-2">
                            <p class="text-sm font-semibold text-gray-900">Adjusted Book Balance</p>
                            <p class="text-sm font-semibold text-gray-900">RM 1,248,995.50</p>
                        </div>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mt-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-red-800">Unreconciled Difference</p>
                        <p class="text-xl font-semibold text-red-700">RM 53,184.50</p>
                    </div>
                    <p class="mt-2 text-sm text-red-600">This difference requires investigation before the reconciliation can be completed.</p>
                </div>
            </div>

            <!-- Signature Section -->
            <div class="p-6 border-t border-[#e5e5e5]">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 mb-8">_________________________</p>
                        <p class="text-sm font-medium text-gray-900">Prepared By</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                        <p class="text-xs text-gray-500">Date: _____________</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-8">_________________________</p>
                        <p class="text-sm font-medium text-gray-900">Reviewed By</p>
                        <p class="text-xs text-gray-500">Manager Name</p>
                        <p class="text-xs text-gray-500">Date: _____________</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-8">_________________________</p>
                        <p class="text-sm font-medium text-gray-900">Approved By</p>
                        <p class="text-xs text-gray-500">Compliance Officer</p>
                        <p class="text-xs text-gray-500">Date: _____________</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
