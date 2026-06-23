<x-app-layout title="Reconciliation Report">
    <div class="space-y-6">
        <x-page-header title="Reconciliation Report" :actions="true">
            Monthly reconciliation summary report

            <x-slot:actions>
                <x-button variant="secondary">Print</x-button>
                <x-button variant="primary">Export PDF</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select
                name="account"
                :options="[
                    'maybank' => 'Maybank Current Account',
                    'cimb' => 'CIMB Business Account',
                    'rhb' => 'RHB Trading Account',
                ]"
                placeholder="All Bank Accounts"
                inline
            />
            <x-select
                name="month"
                :options="[
                    '2026-05' => 'May 2026',
                    '2026-04' => 'April 2026',
                    '2026-03' => 'March 2026',
                ]"
                :selected="'2026-05'"
                placeholder=""
                inline
            />
            <x-button variant="secondary" type="submit">Generate Report</x-button>
        </x-filter-bar>

        <x-card>
            <x-card-section>
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-ink">Bank Reconciliation Report</h2>
                    <p class="text-sm text-ink-muted mt-1">Maybank Current Account - May 2026</p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div>
                        <p class="text-sm text-ink-muted">Report Date</p>
                        <p class="mt-1 text-sm font-medium text-ink">2026-05-03</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Prepared By</p>
                        <p class="mt-1 text-sm font-medium text-ink">Admin User</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Approved By</p>
                        <p class="mt-1 text-sm font-medium text-ink">-</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted">Status</p>
                        <p class="mt-1">
                            <x-badge variant="warning">In Progress</x-badge>
                        </p>
                    </div>
                </div>
            </x-card-section>

            <x-card-section title="Section 1: Balance Comparison">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-card>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-ink">Bank Statement Balance</p>
                            <p class="text-lg font-semibold text-ink">RM 1,250,430.00</p>
                        </div>
                        <p class="text-xs text-ink-muted">As of May 1, 2026</p>
                    </x-card>
                    <x-card>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-ink">Book Balance</p>
                            <p class="text-lg font-semibold text-ink">RM 1,248,920.50</p>
                        </div>
                        <p class="text-xs text-ink-muted">As of May 1, 2026</p>
                    </x-card>
                </div>
            </x-card-section>

            <x-card-section title="Section 2: Adjustments to Bank Balance">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink">Outstanding Checks</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm pl-8">Check #CHK-2021</td>
                            <td class="px-4 py-3 text-sm">2026-04-28</td>
                            <td class="px-4 py-3 text-sm text-right">2026-04-28</td>
                            <td class="px-4 py-3 text-sm text-right">-5,000.00</td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm pl-8">Check #CHK-2022</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">-12,500.00</td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm pl-8">Check #CHK-2023</td>
                            <td class="px-4 py-3 text-sm">2026-05-02</td>
                            <td class="px-4 py-3 text-sm text-right">2026-05-02</td>
                            <td class="px-4 py-3 text-sm text-right">-8,250.00</td>
                        </tr>
                        <tr class="bg-canvas-subtle">
                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink">Total Outstanding Checks</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">-25,750.00</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink pt-4">Deposits in Transit</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm pl-8">Deposit #DEP-0892</td>
                            <td class="px-4 py-3 text-sm">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">2026-04-30</td>
                            <td class="px-4 py-3 text-sm text-right">45,000.00</td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm pl-8">Deposit #DEP-0893</td>
                            <td class="px-4 py-3 text-sm">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">32,500.00</td>
                        </tr>
                        <tr class="bg-canvas-subtle">
                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-ink">Total Deposits in Transit</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">77,500.00</td>
                        </tr>
                    </x-slot:tbody>
                </x-table>
            </x-card-section>

            <x-card-section title="Section 3: Adjustments to Book Balance">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">Bank Service Charge</td>
                            <td class="px-4 py-3 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">-50.00</td>
                        </tr>
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">Interest Income</td>
                            <td class="px-4 py-3 text-sm text-right">2026-05-01</td>
                            <td class="px-4 py-3 text-sm text-right">125.00</td>
                        </tr>
                        <tr class="bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">Total Book Adjustments</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-sm text-right font-medium">75.00</td>
                        </tr>
                    </x-slot:tbody>
                </x-table>
            </x-card-section>

            <x-card-section title="Section 4: Final Reconciliation">
                <x-alert type="success" class="mb-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink">Bank Statement Balance</p>
                            <p class="text-sm font-medium text-ink">RM 1,250,430.00</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink pl-4">Less: Outstanding Checks</p>
                            <p class="text-sm font-medium text-ink">-RM 25,750.00</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink pl-4">Add: Deposits in Transit</p>
                            <p class="text-sm font-medium text-ink">RM 77,500.00</p>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-2">
                            <p class="text-sm font-semibold text-ink">Adjusted Bank Balance</p>
                            <p class="text-sm font-semibold text-ink">RM 1,302,180.00</p>
                        </div>
                    </div>
                </x-alert>

                <x-alert type="info" class="mb-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink">Book Balance</p>
                            <p class="text-sm font-medium text-ink">RM 1,248,920.50</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink pl-4">Add: Bank Adjustments</p>
                            <p class="text-sm font-medium text-ink">RM 75.00</p>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-2">
                            <p class="text-sm font-semibold text-ink">Adjusted Book Balance</p>
                            <p class="text-sm font-semibold text-ink">RM 1,248,995.50</p>
                        </div>
                    </div>
                </x-alert>

                <x-alert type="error">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-danger-text">Unreconciled Difference</p>
                        <p class="text-xl font-semibold text-danger-text">RM 53,184.50</p>
                    </div>
                    <p class="mt-2 text-sm text-danger-text">This difference requires investigation before the reconciliation can be completed.</p>
                </x-alert>
            </x-card-section>

            <x-card-section>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-ink-muted mb-8">_________________________</p>
                        <p class="text-sm font-medium text-ink">Prepared By</p>
                        <p class="text-xs text-ink-muted">Admin User</p>
                        <p class="text-xs text-ink-muted">Date: _____________</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted mb-8">_________________________</p>
                        <p class="text-sm font-medium text-ink">Reviewed By</p>
                        <p class="text-xs text-ink-muted">Manager Name</p>
                        <p class="text-xs text-ink-muted">Date: _____________</p>
                    </div>
                    <div>
                        <p class="text-sm text-ink-muted mb-8">_________________________</p>
                        <p class="text-sm font-medium text-ink">Approved By</p>
                        <p class="text-xs text-ink-muted">Compliance Officer</p>
                        <p class="text-xs text-ink-muted">Date: _____________</p>
                    </div>
                </div>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
