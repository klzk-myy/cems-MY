<x-app-layout title="Rate Analytics — BI">
    <div class="space-y-6">
        <x-page-header
            title="Rate Analytics"
            description="Spread capture, override frequency and deviation from the market mid"
        />

        @include('bi.partials.nav')

        <x-filter-bar method="GET">
            <x-input name="start_date" type="date" label="Start Date" :value="$from" inline />
            <x-input name="end_date" type="date" label="End Date" :value="$to" inline />
            <x-select name="currency" label="Currency" :options="$currencyOptions" :selected="$selected" placeholder="All currencies" inline />
            @if($canSeeAllBranches)
                <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :selected="$branchId" placeholder="All branches" inline />
            @endif
            <x-button variant="primary" type="submit">Apply</x-button>
        </x-filter-bar>

        @include('bi.partials.date-presets')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card :title="$selected === '' ? 'Spread Capture' : ($selected . ' Spread Capture')" description="Volume-weighted sell rate minus buy rate per day">
                <div class="p-4">
                    <x-chart type="line" :labels="$spreadChart['labels']" :datasets="$spreadChart['datasets']" :height="280" />
                </div>
            </x-card>

            <x-card title="Rate Override Frequency" description="Share of the day's transactions priced off the board rate">
                <div class="p-4">
                    <x-chart type="line" :labels="$overrideChart['labels']" :datasets="$overrideChart['datasets']" :height="280" />
                </div>
            </x-card>
        </div>

        <x-card title="Rate vs Market Mid" description="Completed transactions in the period, averaged against the board rate captured at booking">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Rate</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Market Mid</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Deviation</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Overrides</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row['currency_code'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['tx_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['avg_rate'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['avg_base_rate'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['deviation_pct'], 4) }}%</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['override_count']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-sm text-center text-ink-muted">No completed transactions with a board rate in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
