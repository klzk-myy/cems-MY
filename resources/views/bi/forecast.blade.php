<x-app-layout title="Demand Forecast — BI">
    <div class="space-y-6">
        <x-page-header
            title="Currency Demand Forecast"
            description="Predicted buy/sell quantities for the next 7 days — seasonal blend over the analytics rollups"
        />

        @include('bi.partials.nav')

        <x-filter-bar method="GET">
            <x-select name="currency" label="Currency" :options="$currencyOptions" :selected="$selected" placeholder="Select currency" inline />
            @if($canSeeAllBranches)
                <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :selected="$branchId" placeholder="All branches" inline />
            @endif
            <x-button variant="primary" type="submit">Apply</x-button>
        </x-filter-bar>

        @if($selected !== '')
                    @if($insufficientHistory)
            <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                Forecasts are based on less than six months of rollup history. Run
                <code class="font-mono">php artisan analytics:rebuild --from=&lt;date&gt;</code> to backfill,
                then <code class="font-mono">php artisan bi:forecast</code> — accuracy improves with more history.
            </div>
        @endif

        <x-card title="Demand Trend" :description="'Actual vs forecast ' . $selected . ' quantities — ' . $from . ' to ' . $to">
                <div class="p-4">
                    <x-chart type="line" :labels="$chart['labels']" :datasets="$chart['datasets']" :height="320" />
                </div>
            </x-card>

            <x-card title="7-Day Forecast" :description="$selected . ' predicted quantities — dashed line on the chart'">
                <x-table>
                    <x-slot:thead>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Day</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Buy Qty</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Sell Qty</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Buy (MYR)</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Sell (MYR)</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">History</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                    @forelse($forecasts as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row->date->toDateString() }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $row->date->format('D') }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row->predicted_buy_qty, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row->predicted_sell_qty, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row->predicted_buy_volume_myr, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row->predicted_sell_volume_myr, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ $row->sample_days }}d</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-sm text-center text-ink-muted">
                                No forecast yet — run <code>php artisan bi:forecast</code> after the rollups have been rebuilt.
                            </td>
                        </tr>
                    @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @endif

        <x-card title="All Currencies — 7-Day Demand" description="Predicted totals across the forecast horizon for stocking planning">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Buy Qty</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Sell Qty</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Buy (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pred. Sell (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">On Hand</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">History</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($summary as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row['currency_code'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['buy_qty'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['sell_qty'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['buy_volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['sell_volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right {{ $row['restock'] ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-ink dark:text-ink-dark' }}">
                            {{ number_format((float) $row['on_hand_qty'], 2) }}@if($row['restock']) ⚠@endif
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ $row['sample_days'] }}d</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-sm text-center text-ink-muted">No forecast data yet.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
