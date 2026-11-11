<x-app-layout title="Customer Analytics — BI">
    <div class="space-y-6">
        <x-page-header
            title="Customer Analytics"
            description="Active and new customers per month; top accounts by volume"
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
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $customer['full_name'] }}</td>
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
    </div>
</x-app-layout>
