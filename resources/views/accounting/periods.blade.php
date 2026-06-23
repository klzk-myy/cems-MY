<x-app-layout title="Accounting Periods">
    <div class="space-y-6">
        <x-page-header title="Accounting Periods" description="Manage monthly accounting periods" :actions="true">
            <x-slot:actions>
                <x-select name="fiscal_year" :options="['2026' => 'FY 2026', '2025' => 'FY 2025']" placeholder="Fiscal Year" selected="2026" inline />
                <x-button variant="primary">+ New Period</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <x-select name="status" :options="['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived']" placeholder="All Status" inline />
            <x-input name="search" type="text" placeholder="Search periods..." inline class="md:w-64" />
            <x-button variant="secondary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Start Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">End Date</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Revenue</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Expenses</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P01</td>
                        <td class="px-4 py-3 text-sm">January 2026</td>
                        <td class="px-4 py-3 text-sm">2026-01-01</td>
                        <td class="px-4 py-3 text-sm">2026-01-31</td>
                        <td class="px-4 py-3 text-sm text-right">125,430.00</td>
                        <td class="px-4 py-3 text-sm text-right">98,210.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="gray">Closed</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P02</td>
                        <td class="px-4 py-3 text-sm">February 2026</td>
                        <td class="px-4 py-3 text-sm">2026-02-01</td>
                        <td class="px-4 py-3 text-sm">2026-02-28</td>
                        <td class="px-4 py-3 text-sm text-right">132,870.00</td>
                        <td class="px-4 py-3 text-sm text-right">101,450.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="gray">Closed</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P03</td>
                        <td class="px-4 py-3 text-sm">March 2026</td>
                        <td class="px-4 py-3 text-sm">2026-03-01</td>
                        <td class="px-4 py-3 text-sm">2026-03-31</td>
                        <td class="px-4 py-3 text-sm text-right">141,200.00</td>
                        <td class="px-4 py-3 text-sm text-right">108,900.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="gray">Closed</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P04</td>
                        <td class="px-4 py-3 text-sm">April 2026</td>
                        <td class="px-4 py-3 text-sm">2026-04-01</td>
                        <td class="px-4 py-3 text-sm">2026-04-30</td>
                        <td class="px-4 py-3 text-sm text-right">138,500.00</td>
                        <td class="px-4 py-3 text-sm text-right">105,750.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="gray">Closed</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P05</td>
                        <td class="px-4 py-3 text-sm">May 2026</td>
                        <td class="px-4 py-3 text-sm">2026-05-01</td>
                        <td class="px-4 py-3 text-sm">2026-05-31</td>
                        <td class="px-4 py-3 text-sm text-right">95,420.00</td>
                        <td class="px-4 py-3 text-sm text-right">78,900.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="success">Open</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">View</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm">P06</td>
                        <td class="px-4 py-3 text-sm">June 2026</td>
                        <td class="px-4 py-3 text-sm">2026-06-01</td>
                        <td class="px-4 py-3 text-sm">2026-06-30</td>
                        <td class="px-4 py-3 text-sm text-right">-</td>
                        <td class="px-4 py-3 text-sm text-right">-</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="info">Future</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm" disabled>View</x-button>
                        </td>
                    </tr>
                    <tr class="bg-canvas-subtle border-t border-border">
                        <td colspan="4" class="px-4 py-3 text-sm font-medium text-ink">Total YTD</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">633,420.00</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">493,210.00</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-6 of 6 periods</p>
            <div class="flex gap-2">
                <x-button variant="secondary" size="sm" disabled>Previous</x-button>
                <x-button variant="secondary" size="sm" disabled>Next</x-button>
            </div>
        </div>
    </div>
</x-app-layout>
