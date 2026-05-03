<x-app-layout title="Bank Reconciliation">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Bank Reconciliation</h1>
                <p class="mt-1 text-sm text-gray-500">Reconcile bank statements with accounting records</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                + New Reconciliation
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="maybank">Maybank Current Account</option>
                    <option value="cimb">CIMB Business Account</option>
                    <option value="rhb">RHB Trading Account</option>
                </select>
                <input type="date" value="2026-05-01" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Load</button>
            </div>
        </div>

        <!-- Reconciliation Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Bank Statement Balance</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">RM 1,250,430.00</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Book Balance</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">RM 1,248,920.50</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Difference</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">RM 1,509.50</p>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
                <p class="text-sm text-gray-500">Status</p>
                <p class="mt-1">
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                </p>
            </div>
        </div>

        <!-- Reconciliation Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Outstanding Checks -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-[#e5e5e5]">
                    <h3 class="text-sm font-medium text-gray-900">Outstanding Checks</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check No.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cleared</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2021</td>
                            <td class="px-4 py-3 text-sm">2026-04-28</td>
                            <td class="px-4 py-3 text-sm text-right">5,000.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300">
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2022</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">12,500.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300">
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2023</td>
                            <td class="px-4 py-3 text-sm">2026-05-02</td>
                            <td class="px-4 py-3 text-sm text-right">8,250.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300">
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-900">Total Outstanding</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">25,750.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Deposits in Transit -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-[#e5e5e5]">
                    <h3 class="text-sm font-medium text-gray-900">Deposits in Transit</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cleared</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">DEP-0892</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">45,000.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300">
                            </td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono">DEP-0893</td>
                            <td class="px-4 py-3 text-sm">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">32,500.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300">
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-900">Total in Transit</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">77,500.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Adjustments -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e5e5e5]">
                <h3 class="text-sm font-medium text-gray-900">Adjustments Needed</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bank</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Book</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">Bank Fee</td>
                        <td class="px-4 py-3 text-sm">Monthly bank charge - not recorded in books</td>
                        <td class="px-4 py-3 text-sm text-right">-50.00</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">Interest Earned</td>
                        <td class="px-4 py-3 text-sm">Interest credited by bank - not recorded</td>
                        <td class="px-4 py-3 text-sm text-right">125.00</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Cancel</button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Save Draft</button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Complete Reconciliation</button>
        </div>
    </div>
</x-app-layout>
