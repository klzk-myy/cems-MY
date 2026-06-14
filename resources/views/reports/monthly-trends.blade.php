<x-app-layout title="Monthly Transaction Trends">
    <div class="p-6 space-y-6">
        <x-page-header title="Monthly Transaction Trends" description="Analyze transaction volumes and counts by month" :actions="true">
            <x-slot:actions>
                <span class="text-sm text-ink-muted">{{ $year }}</span>
            </x-slot:actions>
        </x-page-header>

        @php
            $yearOptions = [];
            foreach (range(date('Y') - 5, date('Y')) as $y) {
                $yearOptions[$y] = $y;
            }

            $currencyOptions = ['all' => 'All Currencies'];
            foreach ($currencies as $curr) {
                $currencyOptions[$curr] = $curr;
            }
        @endphp

        <x-filter-bar method="GET">
            <x-select name="year" label="Year" :options="$yearOptions" :selected="$year" placeholder="" inline />
            <x-select name="currency" label="Currency" :options="$currencyOptions" :selected="$currency" placeholder="" inline />
            <x-button variant="primary" type="submit">Apply Filters</x-button>
        </x-filter-bar>

        <x-stat-grid cols="4">
            @foreach($trends as $trend)
                <x-card>
                    <div class="p-4">
                        <div class="text-xs font-medium text-ink-muted uppercase mb-1">{{ $trend['month'] }}</div>
                        <div class="text-2xl font-semibold text-ink">{{ number_format($trend['count']) }}</div>
                        <div class="text-sm text-ink-muted mt-1">
                            {{ $currency == 'all' ? 'All' : $currency }} {{ number_format($trend['volume'], 2) }}
                        </div>
                        @if($trend['change'] !== null)
                            <div class="text-xs mt-2 {{ $trend['change'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ $trend['change'] >= 0 ? '+' : '' }}{{ number_format($trend['change'], 1) }}% vs prev month
                            </div>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </x-stat-grid>

        <x-card title="Monthly Breakdown">
            <x-table>
                <x-slot:thead>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume ({{ $currency == 'all' ? 'MYR' : $currency }})</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Value</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">MoM Change</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($monthlyData as $data)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-6 py-4 text-sm text-ink">{{ $data['month'] }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['count']) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['volume'], 2) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($data['avg_value'], 2) }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($data['mom_change'] !== null)
                                    <span class="text-sm {{ $data['mom_change'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                        {{ $data['mom_change'] >= 0 ? '+' : '' }}{{ number_format($data['mom_change'], 1) }}%
                                    </span>
                                @else
                                    <span class="text-sm text-ink-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No data available" :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
