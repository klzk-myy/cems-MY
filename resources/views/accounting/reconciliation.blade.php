<x-app-layout title="Bank Reconciliation">
    <div class="space-y-6">
        <x-page-header title="Bank Reconciliation" :actions="true">
            Reconcile bank statements with accounting records

            <x-slot:actions>
                <x-button variant="primary">+ New Reconciliation</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select
                name="account"
                :options="['maybank' => 'Maybank Current Account', 'cimb' => 'CIMB Business Account', 'rhb' => 'RHB Trading Account']"
                selected="maybank"
                placeholder=""
                inline
            />
            <x-input name="date" type="date" value="2026-05-01" inline />
            <x-button variant="secondary" type="submit">Load</x-button>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Bank Statement Balance" value="RM 1,250,430.00" />
            <x-stat-card label="Book Balance" value="RM 1,248,920.50" />
            <x-stat-card label="Difference" value="RM 1,509.50" color="red" />
            <x-stat-card label="Status" value="In Progress" color="yellow" />
        </x-stat-grid>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Outstanding Checks">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Check No.</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Cleared</th>
                    </x-slot:thead>
                    <x-slot:tbody>
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
                        <tr class="bg-canvas-subtle font-medium">
                            <td colspan="2" class="px-4 py-3 text-sm text-ink">Total Outstanding</td>
                            <td class="px-4 py-3 text-sm text-right">25,750.00</td>
                            <td></td>
                        </tr>
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <x-card title="Deposits in Transit">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Cleared</th>
                    </x-slot:thead>
                    <x-slot:tbody>
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
                        <tr class="bg-canvas-subtle font-medium">
                            <td colspan="2" class="px-4 py-3 text-sm text-ink">Total in Transit</td>
                            <td class="px-4 py-3 text-sm text-right">77,500.00</td>
                            <td></td>
                        </tr>
                    </x-slot:tbody>
                </x-table>
            </x-card>
        </div>

        <x-card title="Adjustments Needed">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Bank</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Book</th>
                </x-slot:thead>
                <x-slot:tbody>
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
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-end gap-3">
            <x-button variant="secondary">Cancel</x-button>
            <x-button variant="secondary">Save Draft</x-button>
            <x-button variant="primary">Complete Reconciliation</x-button>
        </div>
    </div>
</x-app-layout>
