<x-app-layout title="BI Dashboard">
    <div class="space-y-6">
        <x-page-header
            title="Business Intelligence"
            description="Executive KPIs, volume trends, currency mix and margin analytics"
        />

        @include('bi.partials.nav')

        {{-- Filters: same start_date/end_date convention as the report
             pages. The branch selector only renders for viewers who can
             see all branches; scoped viewers are pinned server-side. --}}
        <x-filter-bar method="GET">
            <x-input
                name="start_date"
                type="date"
                label="Start Date"
                :value="$from"
                inline
            />
            <x-input
                name="end_date"
                type="date"
                label="End Date"
                :value="$to"
                inline
            />
            @if($canSeeAllBranches)
                <x-select
                    name="branch_id"
                    label="Branch"
                    :options="$branches->pluck('name', 'id')"
                    :selected="$branchId"
                    placeholder="All branches"
                    inline
                />
            @endif
            <x-button variant="primary" type="submit">Apply</x-button>
        </x-filter-bar>

        @include('bi.partials.date-presets')

        <div class="flex justify-end">
            <form method="POST" action="{{ route('bi.export') }}">
                @csrf
                <input type="hidden" name="type" value="digest">
                <input type="hidden" name="start_date" value="{{ $from }}">
                <input type="hidden" name="end_date" value="{{ $to }}">
                @if($canSeeAllBranches && $branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                <x-button variant="secondary" size="sm" type="submit">Export CSV</x-button>
            </form>
        </div>

        @php
            $delta = fn ($card) => $card['delta'] === null ? null : (float) $card['delta'];
            $money = fn ($v) => number_format((float) $v, 2);
            $approvalSeconds = (float) $cards['avg_approval_seconds']['value'];
            $approvalLabel = $approvalSeconds >= 60
                ? number_format($approvalSeconds / 60, 1) . ' min'
                : number_format($approvalSeconds, 0) . ' s';
        @endphp

        <x-stat-grid>
            <x-stat-card label="Volume (MYR)" :value="$money($cards['volume_myr']['value'])" color="green" :trend="$delta($cards['volume_myr'])" />
            <x-stat-card label="Realized Margin (MYR)" :value="$money($cards['realized_margin_myr']['value'])" color="purple" :trend="$delta($cards['realized_margin_myr'])" />
            <x-stat-card label="Margin %" :value="number_format((float) $cards['margin_pct']['value'], 2) . '%'" color="yellow" :trend="$delta($cards['margin_pct'])" />
            <x-stat-card label="Transactions" :value="number_format($cards['tx_count']['value'])" color="blue" :trend="$delta($cards['tx_count'])" />
            <x-stat-card label="Unique Customers" :value="number_format($cards['unique_customers']['value'])" color="blue" :trend="$delta($cards['unique_customers'])" />
            <x-stat-card label="Avg Ticket (MYR)" :value="$money($cards['avg_ticket_myr']['value'])" color="gray" :trend="$delta($cards['avg_ticket_myr'])" />
            <x-stat-card label="Cancel Rate" :value="number_format((float) $cards['cancel_rate_pct']['value'], 2) . '%'" color="red" :trend="$delta($cards['cancel_rate_pct'])" />
            <x-stat-card label="Avg Approval Time" :value="$approvalLabel" color="gray" :trend="$delta($cards['avg_approval_seconds'])" />
        </x-stat-grid>

        @if($lastComputedAt === null)
            <x-card>
                <div class="p-6 text-sm text-ink-muted">
                    No analytics data yet for this period — rollups are built nightly, or run
                    <code class="font-mono text-xs">php artisan analytics:rebuild --days=30</code>
                    to backfill immediately.
                </div>
            </x-card>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Transaction Volume" :description="'Buy vs sell MYR, daily — ' . $from . ' to ' . $to">
                <div class="p-4">
                    <x-chart type="line" :labels="$volumeChart['labels']" :datasets="$volumeChart['datasets']" />
                </div>
            </x-card>

            <x-card title="Realized Margin Trend" description="Daily realized margin in MYR">
                <div class="p-4">
                    <x-chart type="line" :labels="$marginChart['labels']" :datasets="$marginChart['datasets']" />
                </div>
            </x-card>

            <x-card title="Currency Mix" description="Share of period volume by currency">
                <div class="p-4">
                    <x-chart type="doughnut" :labels="$currencyChart['labels']" :datasets="$currencyChart['datasets']" :height="260" />
                </div>
            </x-card>

            @if($canSeeAllBranches)
                <x-card title="Branch Comparison" description="Volume and realized margin per branch">
                    <div class="p-4">
                        <x-chart type="bar" :labels="$comparisonChart['labels']" :datasets="$comparisonChart['datasets']" :height="260" />
                    </div>
                </x-card>
            @else
                <x-card title="Status Mix" description="Transaction outcomes in period">
                    <div class="p-4">
                        <x-chart type="doughnut" :labels="$comparisonChart['labels']" :datasets="$comparisonChart['datasets']" :height="260" />
                    </div>
                </x-card>
            @endif
        </div>

        <p class="text-xs text-ink-muted">
            Analytics as of {{ $lastComputedAt?->format('Y-m-d H:i') ?? 'never computed' }}.
            Deltas compare against the prior {{ \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1 }}-day period.
        </p>
    </div>
</x-app-layout>
