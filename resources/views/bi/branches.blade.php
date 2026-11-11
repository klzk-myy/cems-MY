<x-app-layout title="Branch Comparison — BI">
    <div class="space-y-6">
        <x-page-header
            title="Branch Comparison"
            description="Volume, margin and productivity by branch"
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

        <div class="flex justify-end">
            <form method="POST" action="{{ route('bi.export') }}">
                @csrf
                <input type="hidden" name="type" value="branches">
                <input type="hidden" name="start_date" value="{{ $from }}">
                <input type="hidden" name="end_date" value="{{ $to }}">
                @if($canSeeAllBranches && $branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                <x-button variant="secondary" size="sm" type="submit">Export CSV</x-button>
            </form>
        </div>

        <x-card title="Volume vs Margin" :description="'Per branch — ' . $from . ' to ' . $to">
            <div class="p-4">
                <x-chart type="bar" :labels="$comparisonChart['labels']" :datasets="$comparisonChart['datasets']" :height="300" />
            </div>
        </x-card>

        <x-card title="Branch Detail">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Branch</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Completed</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Cancelled</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Margin (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Margin %</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Ticket</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Customers</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $row['branch_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['tx_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['completed_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['cancelled_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-ink dark:text-ink-dark">{{ number_format((float) $row['volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['realized_margin_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['margin_pct'], 2) }}%</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format((float) $row['avg_ticket_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($row['unique_customers']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-sm text-center text-ink-muted">No activity in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
