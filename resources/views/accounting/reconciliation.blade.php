<x-app-layout title="Bank Reconciliation">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-ink">Bank Reconciliation</h1>
                <p class="mt-1 text-sm text-ink-muted">Reconcile bank statements with accounting records</p>
            </div>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                + New Reconciliation
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-surface border border-border rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <select class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                    <option value="maybank">Maybank Current Account</option>
                    <option value="cimb">CIMB Business Account</option>
                    <option value="rhb">RHB Trading Account</option>
                </select>
                <input type="date" value="2026-05-01" class="px-4 py-2.5 text-sm bg-surface border border-border rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Load</button>
            </div>
        </div>

        <!-- Reconciliation Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Bank Statement Balance</p>
                <p class="mt-1 text-2xl font-semibold text-ink">RM 1,250,430.00</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Book Balance</p>
                <p class="mt-1 text-2xl font-semibold text-ink">RM 1,248,920.50</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Difference</p>
                <p class="mt-1 text-2xl font-semibold text-red-600">RM 1,509.50</p>
            </div>
            <div class="bg-surface border border-border rounded-xl p-4">
                <p class="text-sm text-ink-muted">Status</p>
                <p class="mt-1">
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">In Progress</span>
                </p>
            </div>
        </div>

        <!-- Reconciliation Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Outstanding Checks -->
            <div class="bg-surface border border-border rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-border">
                    <h3 class="text-sm font-medium text-ink">Outstanding Checks</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-canvas-subtle border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Check No.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Cleared</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2021</td>
                            <td class="px-4 py-3 text-sm">2026-04-28</td>
                            <td class="px-4 py-3 text-sm text-right">5,000.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-border">
                            </td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2022</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">12,500.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-border">
                            </td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">CHK-2023</td>
                            <td class="px-4 py-3 text-sm">2026-05-02</td>
                            <td class="px-4 py-3 text-sm text-right">8,250.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-border">
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-canvas-subtle border-t border-border">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-medium text-ink">Total Outstanding</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">25,750.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Deposits in Transit -->
            <div class="bg-surface border border-border rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-border">
                    <h3 class="text-sm font-medium text-ink">Deposits in Transit</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-canvas-subtle border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Cleared</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">DEP-0892</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">45,000.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-border">
                            </td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">DEP-0893</td>
                            <td class="px-4 py-3 text-sm">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">32,500.00</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" class="h-4 w-4 rounded border-border">
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-canvas-subtle border-t border-border">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-medium text-ink">Total in Transit</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">77,500.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Adjustments -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border">
                <h3 class="text-sm font-medium text-ink">Adjustments Needed</h3>
            </div>
            <table class="w-full">
                <thead class="bg-canvas-subtle border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Bank</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Book</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">Bank Fee</td>
                        <td class="px-4 py-3 text-sm">Monthly bank charge - not recorded in books</td>
                        <td class="px-4 py-3 text-sm text-right">-50.00</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
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
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Cancel</button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border">Save Draft</button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">Complete Reconciliation</button>
        </div>
    </div>
</x-app-layout>
