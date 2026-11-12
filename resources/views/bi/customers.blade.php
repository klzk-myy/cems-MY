<x-app-layout title="Customer Analytics — BI">
    <div class="space-y-6">
        <x-page-header
            title="Customer Analytics"
            description="Concentration, repeat rate, lifetime value, cohort retention and top accounts"
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

        <div class="flex justify-end">
            <form method="POST" action="{{ route('bi.export') }}">
                @csrf
                <input type="hidden" name="type" value="customers">
                <input type="hidden" name="start_date" value="{{ $from }}">
                <input type="hidden" name="end_date" value="{{ $to }}">
                @if($canSeeAllBranches && $branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                <x-button variant="secondary" size="sm" type="submit">Export CSV</x-button>
            </form>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Top-10 Concentration" :value="number_format((float) $analytics['concentration_top10_pct'], 2) . '%'" color="blue" />
            <x-stat-card label="Repeat Rate" :value="number_format((float) $analytics['repeat_rate_pct'], 2) . '%'" color="green" />
            <x-stat-card label="Avg Txns / Active" :value="number_format((float) $analytics['avg_txn_per_active'], 2)" color="purple" />
            <x-stat-card label="Avg Lifetime Value" :value="'RM ' . number_format((float) $analytics['avg_ltv_myr'], 2)" color="yellow" />
        </div>

        <x-card title="Active Customers" description="Monthly distinct customers, split new vs returning">
            <div class="p-4">
                <x-chart
                    type="bar"
                    :labels="$activeChart['labels']"
                    :datasets="$activeChart['datasets']"
                    :height="300"
                    :options="['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]]"
                />
            </div>
        </x-card>

        <x-card title="Top Customers" :description="'Top ' . count($topCustomers) . ' by period volume'">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">#</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Margin (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Currencies</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($topCustomers as $index => $customer)
                    <tr>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark"><x-customer-link :customer="$customerModels->get($customer['customer_id'])" /></td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($customer['tx_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-ink dark:text-ink-dark">{{ number_format((float) $customer['volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $customer['realized_margin_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($customer['currencies_used']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-sm text-center text-ink-muted">No customer activity in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Cohort Retention" description="Customers grouped by the month of their first transaction, then counted active in each later month">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Cohort</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Size</th>
                    @foreach(($analytics['cohorts'][0]['cells'] ?? []) as $cell)
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">{{ \Carbon\Carbon::parse($cell['month'].'-01')->format('M Y') }}</th>
                    @endforeach
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($analytics['cohorts'] as $cohort)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ \Carbon\Carbon::parse($cohort['cohort_month'].'-01')->format('M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($cohort['cohort_size']) }}</td>
                        @foreach($cohort['cells'] as $cell)
                            <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ $cell['active'] }} ({{ $cell['pct'] }}%)</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-sm text-center text-ink-muted">No customer cohorts in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
