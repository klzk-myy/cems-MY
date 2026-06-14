<x-app-layout title="Budget Management">
    <div class="space-y-6">
        <x-page-header title="Budget Management" :actions="true">
            Manage annual budgets and variances

            <x-slot:actions>
                <x-button variant="primary" icon="M12 4v16m8-8H4">Create Budget</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Budget" value="RM 1,250,000.00" />
            <x-stat-card label="YTD Actual" value="RM 875,420.50" />
            <x-stat-card label="YTD Variance" value="RM 374,579.50" color="green" />
            <x-stat-card label="% Used" value="70.0%" />
        </x-stat-grid>

        <x-filter-bar>
            <x-select name="fiscal_year" :options="['2026' => 'Fiscal Year 2026', '2025' => 'Fiscal Year 2025']" inline />
            <x-select name="department" :options="['' => 'All Departments', 'operations' => 'Operations', 'compliance' => 'Compliance', 'finance' => 'Finance']" inline />
            <x-input name="search" type="text" placeholder="Search accounts..." inline class="md:w-64" />
            <x-button variant="secondary" type="submit">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Annual Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">YTD Actual</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">YTD Budget</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Variance</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">% Used</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm font-mono">5100-001</td>
                        <td class="px-4 py-3 text-sm">Currency Exchange Revenue</td>
                        <td class="px-4 py-3 text-sm text-right">500,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">350,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">291,666.67</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+58,333.33</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="warning">70%</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">Edit</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm font-mono">6100-001</td>
                        <td class="px-4 py-3 text-sm">Staff Salaries</td>
                        <td class="px-4 py-3 text-sm text-right">400,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">280,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">233,333.33</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+46,666.67</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="warning">70%</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">Edit</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm font-mono">6200-001</td>
                        <td class="px-4 py-3 text-sm">Office Rent</td>
                        <td class="px-4 py-3 text-sm text-right">150,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">107,500.00</td>
                        <td class="px-4 py-3 text-sm text-right">87,500.00</td>
                        <td class="px-4 py-3 text-sm text-right text-danger-text">-20,000.00</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="danger">72%</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">Edit</x-button>
                        </td>
                    </tr>
                    <tr class="hover:bg-canvas-subtle">
                        <td class="px-4 py-3 text-sm font-mono">6300-001</td>
                        <td class="px-4 py-3 text-sm">Compliance Costs</td>
                        <td class="px-4 py-3 text-sm text-right">200,000.00</td>
                        <td class="px-4 py-3 text-sm text-right">137,920.50</td>
                        <td class="px-4 py-3 text-sm text-right">116,666.67</td>
                        <td class="px-4 py-3 text-sm text-right text-success-text">+21,253.83</td>
                        <td class="px-4 py-3 text-center"><x-badge variant="warning">69%</x-badge></td>
                        <td class="px-4 py-3 text-center">
                            <x-button variant="ghost" size="sm">Edit</x-button>
                        </td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-4 of 4 accounts</p>
            <div class="flex gap-2">
                <x-button variant="secondary" size="sm" disabled>Previous</x-button>
                <x-button variant="secondary" size="sm" disabled>Next</x-button>
            </div>
        </div>
    </div>
</x-app-layout>
