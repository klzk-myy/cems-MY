<x-app-layout title="Risk Trends">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Risk Trends"
            description="Historical risk metrics and analysis"
        />

        <x-filter-bar>
            <x-select
                name="range"
                :options="['30' => 'Last 30 Days', '90' => 'Last 90 Days', '180' => 'Last 6 Months', '365' => 'Last Year']"
                inline
            />
            <x-select
                name="branch"
                :options="['' => 'All Branches', 'kl' => 'Kuala Lumpur', 'penang' => 'Penang', 'johor' => 'Johor']"
                inline
            />
            <x-button variant="primary" type="submit">Apply Filter</x-button>
        </x-filter-bar>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-chart-trend
                title="High Risk Customer Trend"
                :labels="$highRiskTrend['labels']"
                :values="$highRiskTrend['values']"
                color="red"
            />

            <x-chart-trend
                title="Alert Volume Trend"
                :labels="$alertVolumeTrend['labels']"
                :values="$alertVolumeTrend['values']"
                color="yellow"
            />
        </div>

        <x-card title="Risk Score Distribution Over Time">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">High Risk</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Medium Risk</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Low Risk</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Total</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">January 2024</td>
                        <td class="px-4 py-3 text-sm text-danger-text font-medium">12</td>
                        <td class="px-4 py-3 text-sm text-warning-text">28</td>
                        <td class="px-4 py-3 text-sm text-success-text">156</td>
                        <td class="px-4 py-3 text-sm text-ink">196</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">December 2023</td>
                        <td class="px-4 py-3 text-sm text-danger-text font-medium">8</td>
                        <td class="px-4 py-3 text-sm text-warning-text">24</td>
                        <td class="px-4 py-3 text-sm text-success-text">148</td>
                        <td class="px-4 py-3 text-sm text-ink">180</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink">November 2023</td>
                        <td class="px-4 py-3 text-sm text-danger-text font-medium">6</td>
                        <td class="px-4 py-3 text-sm text-warning-text">22</td>
                        <td class="px-4 py-3 text-sm text-success-text">142</td>
                        <td class="px-4 py-3 text-sm text-ink">170</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Key Insights">
            <div class="space-y-3">
                <x-alert type="danger" title="High Risk Customers Increased" :icon="true" class="mb-0">
                    High risk customer count increased by 50% compared to last month
                </x-alert>

                <x-alert type="warning" title="Alert Volume Up" :icon="true" class="mb-0">
                    Total alerts increased by 41% month-over-month
                </x-alert>

                <x-alert type="success" title="EDD Completion Rate Good" :icon="true" class="mb-0">
                    95% of EDD reviews completed within SLA
                </x-alert>
            </div>
        </x-card>
    </div>
</x-app-layout>
