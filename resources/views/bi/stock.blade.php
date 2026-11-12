<x-app-layout title="Stock & Risk — BI">
    <div class="space-y-6">
        <x-page-header
            title="Stock & Risk"
            description="Position exposure and limit utilization, turnover, and the compliance snapshot"
        />

        @include('bi.partials.nav')

        <x-filter-bar method="GET">
            <x-input name="start_date" type="date" label="Start Date" :value="$from" inline />
            <x-input name="end_date" type="date" label="End Date" :value="$to" inline />
            @if($canSeeAllBranches)
                <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :selected="$branchId" placeholder="All branches" inline />
            @endif
            <x-button variant="primary" type="submit">Apply</x-button>
        </x-filter-bar>

        @include('bi.partials.date-presets')

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Exposure" :value="'RM ' . number_format((float) $totals['exposure_myr'], 2)" color="blue" />
            <x-stat-card label="Unrealized P&amp;L" :value="'RM ' . number_format((float) $totals['unrealized_gain_loss_myr'], 2)" color="purple" />
            <x-stat-card label="Unrealized %" :value="number_format((float) $totals['unrealized_pct'], 2) . '%'" color="yellow" />
            <x-stat-card label="Open Alerts" :value="number_format((int) $compliance['open_alerts'])" color="red" />
        </div>

        <x-card title="Position Exposure" description="Quantity, valuation, limit utilization, turnover and days of stock per currency">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Quantity</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Cost</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Rate</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Current Value</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Unrealized P&amp;L</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position Limit</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Utilization</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Turnover</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Days of Stock</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row['currency_code'] }} <span class="text-ink-muted">{{ $row['currency_name'] }}</span></td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['quantity'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['average_cost'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['current_rate'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['current_value'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['unrealized_gain_loss'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ $row['position_limit'] === null ? 'N/A' : number_format((float) $row['position_limit'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['utilization_pct'], 2) }}%</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['turnover_ratio'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['days_of_stock'], 1) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-sm text-center text-ink-muted">No currency positions.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Compliance Snapshot" description="Read-only view of the AML/CFT posture — detailed work stays on the compliance pages">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Metric</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Value</th>
                </x-slot:thead>
                <x-slot:tbody>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">Completed transactions in period</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((int) $compliance['completed_transactions']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">Flagged transactions in period</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((int) $compliance['flagged_transactions']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">Flagged rate</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $compliance['flagged_rate_pct'], 4) }}%</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">Open alerts</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((int) $compliance['open_alerts']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">Open flags</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((int) $compliance['open_flags']) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink dark:text-ink-dark">STR reports filed</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((int) $compliance['str_reports']) }}</td>
                    </tr>
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
