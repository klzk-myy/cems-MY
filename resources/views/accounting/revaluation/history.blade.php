<x-app-layout title="Revaluation History">
    <div class="space-y-6">
        <x-page-header title="Revaluation History" :actions="true">
            View historical currency revaluation records

            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.revaluation') }}">Back to Revaluation</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select name="fiscal_year" :options="['2026' => 'FY 2026', '2025' => 'FY 2025']" placeholder="All Fiscal Years" inline />
            <x-select name="currency" :options="['USD' => 'USD', 'SGD' => 'SGD', 'GBP' => 'GBP', 'EUR' => 'EUR']" placeholder="All Currencies" inline />
            <x-input name="search" type="text" placeholder="Search..." inline class="md:w-64" />
            <x-button variant="secondary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-stat-grid cols="3">
            <x-stat-card label="Total Revaluations" value="12" />
            <x-stat-card label="Total Gains YTD" value="RM 28,450.00" color="green" />
            <x-stat-card label="Total Losses YTD" value="RM 12,350.00" color="red" />
        </x-stat-grid>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Base Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">End Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Gain/Loss</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">50,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6800</td>
                        <td class="px-4 py-3 text-sm text-right">4.7000</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+1,000.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">25,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.4800</td>
                        <td class="px-4 py-3 text-sm text-right">3.4950</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+375.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">GBP</td>
                        <td class="px-4 py-3 text-sm text-right">10,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.9700</td>
                        <td class="px-4 py-3 text-sm text-right">5.9600</td>
                        <td class="px-4 py-3 text-sm text-right text-danger-text">-100.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">45,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6500</td>
                        <td class="px-4 py-3 text-sm text-right">4.6800</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+1,350.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">EUR</td>
                        <td class="px-4 py-3 text-sm text-right">20,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">5.1200</td>
                        <td class="px-4 py-3 text-sm text-right">5.1550</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+700.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-02-28</td>
                        <td class="px-4 py-3 text-sm">P02</td>
                        <td class="px-4 py-3 text-sm">SGD</td>
                        <td class="px-4 py-3 text-sm text-right">30,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">3.5100</td>
                        <td class="px-4 py-3 text-sm text-right">3.4800</td>
                        <td class="px-4 py-3 text-sm text-right text-danger-text">-900.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">2026-01-31</td>
                        <td class="px-4 py-3 text-sm">P01</td>
                        <td class="px-4 py-3 text-sm">USD</td>
                        <td class="px-4 py-3 text-sm text-right">40,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">4.6200</td>
                        <td class="px-4 py-3 text-sm text-right">4.6500</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+1,200.00</td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-7 of 12 records</p>
            <div class="flex gap-2">
                <x-button variant="secondary" size="sm" disabled>Previous</x-button>
                <x-button variant="secondary" size="sm">Next</x-button>
            </div>
        </div>
    </div>
</x-app-layout>
