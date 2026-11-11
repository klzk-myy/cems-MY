<?php

namespace App\Services\Bi;

use App\Models\Bi\AnalyticsDailyBranch;
use App\Models\Bi\AnalyticsDailyCurrency;
use App\Models\Transaction;
use App\Services\System\MathService;
use Carbon\Carbon;

/**
 * Computes executive-dashboard KPIs from the analytics_* rollup tables
 * (built by AnalyticsSnapshotBuilder). All money math runs through
 * MathService/BCMath; deltas compare the requested period against the
 * equally-long window ending the day before it starts.
 */
class KpiService
{
    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * KPI card values for [from, to] with % deltas vs the prior
     * equal-length period. Branch-scoped when $branchId is set.
     *
     * @return array{
     *     volume_myr: array{value: string|int, delta: ?string},
     *     tx_count: array{value: string|int, delta: ?string},
     *     avg_ticket_myr: array{value: string|int, delta: ?string},
     *     realized_margin_myr: array{value: string|int, delta: ?string},
     *     margin_pct: array{value: string|int, delta: ?string},
     *     unique_customers: array{value: string|int, delta: ?string},
     *     cancel_rate_pct: array{value: string|int, delta: ?string},
     *     avg_approval_seconds: array{value: string|int, delta: ?string},
     *     rate_override_count: array{value: string|int, delta: ?string}
     * }
     */
    public function cards(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $priorTo = $from->copy()->subDay();
        $priorFrom = $priorTo->copy()->subDays($days - 1);

        $current = $this->summarize($from, $to, $branchId);
        $prior = $this->summarize($priorFrom, $priorTo, $branchId);

        return [
            'volume_myr' => $this->deltaCard($current['volume_myr'], $prior['volume_myr']),
            'tx_count' => $this->deltaCard($current['tx_count'], $prior['tx_count'], cast: 'int'),
            'avg_ticket_myr' => $this->deltaCard($current['avg_ticket_myr'], $prior['avg_ticket_myr']),
            'realized_margin_myr' => $this->deltaCard($current['realized_margin_myr'], $prior['realized_margin_myr']),
            'margin_pct' => $this->deltaCard($current['margin_pct'], $prior['margin_pct']),
            'unique_customers' => $this->deltaCard(
                (string) $this->distinctCustomers($from, $to, $branchId),
                (string) $this->distinctCustomers($priorFrom, $priorTo, $branchId),
                cast: 'int'
            ),
            'cancel_rate_pct' => $this->deltaCard($current['cancel_rate_pct'], $prior['cancel_rate_pct']),
            'avg_approval_seconds' => $this->deltaCard($current['avg_approval_seconds'], $prior['avg_approval_seconds']),
            'rate_override_count' => $this->deltaCard($current['rate_override_count'], $prior['rate_override_count'], cast: 'int'),
        ];
    }

    /**
     * Daily series for the volume-trend and margin-trend charts.
     * Days with no rollups are zero-filled so the chart shows gaps as 0,
     * not a broken line.
     *
     * @return array{labels: array<int, string>, buy: array<int, string>, sell: array<int, string>, margin: array<int, string>}
     */
    public function dailyTrends(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(buy_volume_myr) as buy_volume')
            ->selectRaw('SUM(sell_volume_myr) as sell_volume')
            ->selectRaw('SUM(realized_margin_myr) as margin')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $labels = $buy = $sell = $margin = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows->get($key);
            $labels[] = $day->format('M j');
            $buy[] = $row->buy_volume ?? '0';
            $sell[] = $row->sell_volume ?? '0';
            $margin[] = $row->margin ?? '0';
        }

        return compact('labels', 'buy', 'sell', 'margin');
    }

    /**
     * Currency mix for the donut/stacked chart: volume per currency,
     * descending.
     *
     * @return array<int, array{currency_code: string, volume_myr: string, share_pct: string}>
     */
    public function currencyMix(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('currency_code')
            ->selectRaw('currency_code')
            ->selectRaw('SUM(buy_volume_myr + sell_volume_myr) as volume')
            ->orderByDesc('volume')
            ->toBase()
            ->get();

        $total = $rows->reduce(
            fn ($carry, $row) => $this->mathService->add($carry, (string) $row->volume),
            '0'
        );

        return $rows->map(fn ($row) => [
            'currency_code' => $row->currency_code,
            'volume_myr' => (string) $row->volume,
            'share_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide((string) $row->volume, $total, 6),
                '100', 2
            ),
        ])->all();
    }

    /**
     * Status mix over the period — the scoped-user counterpart to
     * branchComparison for the dashboard's fourth chart slot.
     *
     * @return array{completed: int, cancelled: int, rejected: int, other: int}
     */
    public function statusMix(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $row = AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->selectRaw('SUM(tx_count) as tx_count')
            ->selectRaw('SUM(completed_count) as completed')
            ->selectRaw('SUM(cancelled_count) as cancelled')
            ->selectRaw('SUM(rejected_count) as rejected')
            ->toBase()
            ->first();

        $completed = (int) ($row->completed ?? 0);
        $cancelled = (int) ($row->cancelled ?? 0);
        $rejected = (int) ($row->rejected ?? 0);
        $total = (int) ($row->tx_count ?? 0);

        return [
            'completed' => $completed,
            'cancelled' => $cancelled,
            'rejected' => $rejected,
            'other' => max(0, $total - $completed - $cancelled - $rejected),
        ];
    }

    /**
     * Latest rollup computation time, for the freshness note on the page.
     */
    public function lastComputedAt(?int $branchId): ?Carbon
    {
        $computedAt = AnalyticsDailyBranch::query()
            ->forBranch($branchId)
            ->max('computed_at');

        return $computedAt === null ? null : Carbon::parse($computedAt);
    }

    /**
     * Per-branch comparison — only for viewers who can see all branches.
     * Margin comes from the currency rollup (the only table holding it),
     * grouped per branch and merged in PHP.
     *
     * @return array<int, array{branch_id: int|null, branch_name: string, volume_myr: string, realized_margin_myr: string, tx_count: int}>
     */
    public function branchComparison(Carbon $from, Carbon $to): array
    {
        $margins = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('branch_id')
            ->selectRaw('branch_id')
            ->selectRaw('SUM(realized_margin_myr) as margin')
            ->toBase()
            ->pluck('margin', 'branch_id');

        return AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->leftJoin('branches', 'branches.id', '=', 'analytics_daily_branch.branch_id')
            ->groupBy('analytics_daily_branch.branch_id', 'branches.name')
            ->selectRaw('analytics_daily_branch.branch_id')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('SUM(analytics_daily_branch.buy_volume_myr + analytics_daily_branch.sell_volume_myr) as volume')
            ->selectRaw('SUM(analytics_daily_branch.tx_count) as tx_count')
            ->orderByDesc('volume')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'branch_id' => $row->branch_id,
                'branch_name' => $row->branch_name ?? 'Unassigned',
                'volume_myr' => (string) $row->volume,
                'realized_margin_myr' => (string) ($margins[$row->branch_id] ?? '0'),
                'tx_count' => (int) $row->tx_count,
            ])
            ->all();
    }

    /**
     * Scalar summary over the branch rollup for one period.
     *
     * @return array<string, string|int>
     */
    protected function summarize(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $row = AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->selectRaw('SUM(tx_count) as tx_count')
            ->selectRaw('SUM(completed_count) as completed_count')
            ->selectRaw('SUM(cancelled_count) as cancelled_count')
            ->selectRaw('SUM(approved_count) as approved_count')
            ->selectRaw('SUM(approval_seconds_total) as approval_seconds_total')
            ->selectRaw('SUM(rate_override_count) as rate_override_count')
            ->selectRaw('SUM(buy_volume_myr) as buy_volume')
            ->selectRaw('SUM(sell_volume_myr) as sell_volume')
            ->toBase()
            ->first();

        $buy = (string) ($row->buy_volume ?? '0');
        $sell = (string) ($row->sell_volume ?? '0');
        $volume = $this->mathService->add($buy, $sell);
        $txCount = (int) ($row->tx_count ?? 0);
        $completedCount = (int) ($row->completed_count ?? 0);
        $cancelledCount = (int) ($row->cancelled_count ?? 0);
        $approvedCount = (int) ($row->approved_count ?? 0);
        $approvalSeconds = (string) ($row->approval_seconds_total ?? '0');

        $margin = $this->totalMargin($from, $to, $branchId);

        return [
            'volume_myr' => $volume,
            'tx_count' => $txCount,
            'avg_ticket_myr' => $this->mathService->safeDivide($volume, (string) $completedCount, 4),
            'realized_margin_myr' => $margin,
            'margin_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide($margin, $sell, 6),
                '100', 4
            ),
            'cancel_rate_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide((string) $cancelledCount, (string) $txCount, 6),
                '100', 4
            ),
            'avg_approval_seconds' => $this->mathService->safeDivide($approvalSeconds, (string) $approvedCount, 2),
            'rate_override_count' => (int) ($row->rate_override_count ?? 0),
        ];
    }

    /**
     * Realized margin for the period from the currency rollup.
     */
    protected function totalMargin(Carbon $from, Carbon $to, ?int $branchId): string
    {
        $margin = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->sum('realized_margin_myr');

        return (string) ($margin ?? '0');
    }

    /**
     * Distinct completed-transaction customers in the period. This one
     * query must hit the transactions table — daily rollups cannot dedupe
     * a customer who traded on multiple days.
     */
    protected function distinctCustomers(Carbon $from, Carbon $to, ?int $branchId): int
    {
        return Transaction::query()
            ->completed()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->forBranch($branchId)
            ->distinct()
            ->count('customer_id');
    }

    /**
     * Wrap a current/prior pair into {value, delta%} — delta is null when
     * the prior period has no baseline to compare against.
     *
     * @param  string  $cast  'string' passes the value through; 'int' casts.
     * @return array{value: string|int, delta: ?string}
     */
    protected function deltaCard(string|int $current, string|int $prior, string $cast = 'string'): array
    {
        $current = (string) $current;
        $prior = (string) $prior;

        $delta = null;
        if ($this->mathService->compare($prior, '0') > 0) {
            $ratio = $this->mathService->divide(
                $this->mathService->subtract($current, $prior),
                $prior,
                6
            );
            $delta = $this->mathService->multiply($ratio, '100', 2);
        }

        return [
            'value' => $cast === 'int' ? (int) $current : $current,
            'delta' => $delta,
        ];
    }
}
