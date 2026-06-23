<x-app-layout title="Currency Revaluation">
    <div class="space-y-6">
        <x-page-header
            title="Currency Revaluation"
            description="Revalue currency positions based on exchange rates"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="primary">Run Revaluation</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-alert type="info" title="Monthly Revaluation Required" :icon="true">
            Run revaluation to update currency position values based on current exchange rates.
        </x-alert>

        <x-card title="Current Exchange Rates">
            <x-stat-grid cols="4">
                <div>
                    <x-stat-card label="USD/MYR" value="4.7200" />
                    <p class="mt-1 text-xs text-success-text">+0.0200 from base</p>
                </div>
                <div>
                    <x-stat-card label="SGD/MYR" value="3.5100" />
                    <p class="mt-1 text-xs text-success-text">+0.0150 from base</p>
                </div>
                <div>
                    <x-stat-card label="GBP/MYR" value="5.9500" />
                    <p class="mt-1 text-xs text-danger-text">-0.0100 from base</p>
                </div>
                <div>
                    <x-stat-card label="EUR/MYR" value="5.1800" />
                    <p class="mt-1 text-xs text-success-text">+0.0250 from base</p>
                </div>
            </x-stat-grid>
        </x-card>

        <x-card title="Revaluation Preview - May 2026">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Value (MYR)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Value (MYR)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Gain/Loss</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">50,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.7000</td>
                        <td class="px-4 py-3 text-sm text-right">4.7200</td>
                        <td class="px-4 py-3 text-sm text-right">235,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">236,000.00</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+1,000.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">25,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.4950</td>
                        <td class="px-4 py-3 text-sm text-right">3.5100</td>
                        <td class="px-4 py-3 text-sm text-right">87,375.00</td>
                        <td class="px-4 py-3 text-sm text-right">87,750.00</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+375.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">GBP</td>
                        <td class="px-4 py-3 text-sm text-right">10,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.9600</td>
                        <td class="px-4 py-3 text-sm text-right">5.9500</td>
                        <td class="px-4 py-3 text-sm text-right">59,600.00</td>
                        <td class="px-4 py-3 text-sm text-right">59,500.00</td>
                        <td class="px-4 py-3 text-sm text-right text-danger-text">-100.00</td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">EUR</td>
                        <td class="px-4 py-3 text-sm text-right">15,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.1550</td>
                        <td class="px-4 py-3 text-sm text-right">5.1800</td>
                        <td class="px-4 py-3 text-sm text-right">77,325.00</td>
                        <td class="px-4 py-3 text-sm text-right">77,700.00</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+375.00</td>
                    </tr>
                    <tr class="bg-canvas-subtle border-t-2 border-border font-medium">
                        <td colspan="5" class="px-4 py-3 text-sm text-ink">Total</td>
                        <td class="px-4 py-3 text-sm text-right">460,950.00</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+1,650.00</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card-section
            title="Last Revaluation"
            description="April 30, 2026"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.revaluation.history') }}">View History</x-button>
            </x-slot:actions>
        </x-card-section>

        <div class="flex items-center justify-end gap-3">
            <x-button variant="secondary">Cancel</x-button>
            <x-button variant="primary">Confirm Revaluation</x-button>
        </div>
    </div>
</x-app-layout>
