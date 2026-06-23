<x-app-layout title="Export Reconciliation">
    <div class="space-y-6">
        <x-page-header
            title="Export Reconciliation"
            description="Export reconciliation report for bank submission"
        />

        <x-card title="Export Options">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-select
                    name="bank_account"
                    label="Bank Account"
                    :options="['maybank' => 'Maybank Current Account', 'cimb' => 'CIMB Business Account', 'rhb' => 'RHB Trading Account']"
                    inline
                />
                <x-input
                    name="reconciliation_date"
                    type="date"
                    label="Reconciliation Date"
                    value="2026-05-01"
                    inline
                />
                <x-select
                    name="format"
                    label="Format"
                    :options="['pdf' => 'PDF Document', 'excel' => 'Excel Spreadsheet', 'csv' => 'CSV File']"
                    inline
                />
                <x-select
                    name="language"
                    label="Language"
                    :options="['en' => 'English', 'ms' => 'Bahasa Malaysia']"
                    inline
                />
            </div>
        </x-card>

        <x-card title="Preview">
            <div class="space-y-6">
                <div class="text-center border-b border-border pb-4">
                    <h2 class="text-xl font-semibold text-ink">Bank Reconciliation Statement</h2>
                    <p class="text-sm text-ink-muted">Maybank Current Account - May 2026</p>
                </div>

                <x-stat-grid cols="2">
                    <x-stat-card label="Bank Statement Balance" value="RM 1,250,430.00" />
                    <x-stat-card label="Book Balance" value="RM 1,248,920.50" />
                </x-stat-grid>

                <x-card-section title="Adjustments">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-2 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-ink-muted uppercase">Amount (RM)</th>
                        </x-slot:thead>
                        <x-slot:tbody>
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
                        </x-slot:tbody>
                        <x-slot:tfoot>
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium">Total Adjustments</td>
                                <td class="px-4 py-2 text-sm text-right font-medium">51,825.00</td>
                            </tr>
                        </x-slot:tfoot>
                    </x-table>
                </x-card-section>

                <x-alert type="success">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium">Reconciled Balance</p>
                            <p class="text-sm">Bank = Book after adjustments</p>
                        </div>
                        <p class="text-2xl font-semibold">RM 1,248,920.50</p>
                    </div>
                </x-alert>
            </div>
        </x-card>

        <div class="flex items-center justify-end gap-3">
            <x-button variant="secondary" href="{{ route('accounting.reconciliation') }}">Cancel</x-button>
            <x-button variant="secondary">Download PDF</x-button>
            <x-button variant="primary">Export</x-button>
        </div>
    </div>
</x-app-layout>
