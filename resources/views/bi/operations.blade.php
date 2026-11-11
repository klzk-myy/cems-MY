<x-app-layout title="Operations — BI">
    <div class="space-y-6">
        <x-page-header
            title="Operations"
            description="Approval turnaround, outcome rates and teller productivity"
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
                <input type="hidden" name="type" value="tellers">
                <input type="hidden" name="start_date" value="{{ $from }}">
                <input type="hidden" name="end_date" value="{{ $to }}">
                @if($canSeeAllBranches && $branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                <x-button variant="secondary" size="sm" type="submit">Export CSV</x-button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Approval Turnaround" description="Average seconds from creation to approval, daily">
                <div class="p-4">
                    <x-chart type="line" :labels="$approvalChart['labels']" :datasets="$approvalChart['datasets']" :height="280" />
                </div>
            </x-card>

            <x-card title="Outcome Rates" description="Cancel and reject share of daily transactions">
                <div class="p-4">
                    <x-chart type="line" :labels="$rateChart['labels']" :datasets="$rateChart['datasets']" :height="280" />
                </div>
            </x-card>
        </div>

        <x-card title="Teller Productivity" description="Ordered by period volume; approval time measures the teller's own transactions">
            <x-table>
                <x-slot:thead>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Teller</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Cancelled</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Rejected</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Rate Overrides</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Approval</th>
                </x-slot:thead>
                <x-slot:tbody>
                @forelse($tellers as $teller)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-ink dark:text-ink-dark">{{ $teller['teller_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($teller['tx_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-ink dark:text-ink-dark">{{ number_format((float) $teller['volume_myr'], 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($teller['cancelled_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($teller['rejected_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">{{ number_format($teller['rate_override_count']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-ink dark:text-ink-dark">
                            @php($secs = (float) $teller['avg_approval_seconds'])
                            {{ $secs >= 60 ? number_format($secs / 60, 1) . ' min' : number_format($secs, 0) . ' s' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-sm text-center text-ink-muted">No teller activity in this period.</td>
                    </tr>
                @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
