<x-app-layout title="Currency Analytics — BI">
    <div class="space-y-6">
        <x-page-header
            title="Currency Analytics"
            description="Per-currency volume, margin and trend deep-dive"
        />

        @include('bi.partials.nav')

        <x-filter-bar method="GET">
            <x-input name="start_date" type="date" label="Start Date" :value="$from" inline />
            <x-input name="end_date" type="date" label="End Date" :value="$to" inline />
            <x-select name="currency" label="Currency" :options="$currencyOptions" :selected="$selected" placeholder="Top currency" inline />
            @if($canSeeAllBranches)
                <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')" :selected="$branchId" placeholder="All branches" inline />
            @endif
            <x-button variant="primary" type="submit">Apply</x-button>
        </x-filter-bar>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('bi.export') }}">
                @csrf
                <input type="hidden" name="type" value="currencies">
                <input type="hidden" name="start_date" value="{{ $from }}">
                <input type="hidden" name="end_date" value="{{ $to }}">
                @if($canSeeAllBranches && $branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                <x-button variant="secondary" size="sm" type="submit">Export CSV</x-button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Currency Mix" description="Share of period volume">
                <div class="p-4">
                    <x-chart type="doughnut" :labels="$mixChart['labels']" :datasets="$mixChart['datasets']" :height="280" />
                </div>
            </x-card>

            <x-card :title="$selected === '' ? 'Currency Trend' : ($selected . ' Trend')" description="Daily buy vs sell MYR">
                <div class="p-4">
                    <x-chart type="line" :labels="$trendChart['labels']" :datasets="$trendChart['datasets']" :height="280" />
                </div>
            </x-card>
        </div>

        <x-card :title="$selected === '' ? 'Realized Margin' : ($selected . ' Realized Margin')" description="Daily realized margin in MYR">
            <div class="p-4">
                <x-chart type="line" :labels="$marginChart['labels']" :datasets="$marginChart['datasets']" :height="220" />
            </div>
        </x-card>

        <x-card title="Currency Breakdown">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Bought Qty</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sold Qty</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Buy Volume (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sell Volume (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total Volume</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Margin (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Share</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row['currency_code'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['buy_qty'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['sell_qty'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['buy_volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['sell_volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-ink dark:text-ink-dark">{{ number_format((float) $row['volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['realized_margin_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['share_pct'], 2) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-sm text-center text-ink-muted">No completed transactions in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
