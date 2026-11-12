<?php

namespace App\Services\Bi;

use App\Models\Bi\AnalyticsDailyBranch;
use App\Models\Bi\AnalyticsDailyCurrency;
use App\Models\Bi\AnalyticsDailyTeller;
use App\Models\Bi\AnalyticsMonthlyCustomer;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Compliance\StrReport;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        protected ThresholdService $thresholdService,
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
     * Daily series for each dashboard card, used to render sparklines.
     * Volume, ticket, cancel rate and turnaround come from the branch
     * rollup; margin needs the currency rollup.
     *
     * @return array<string, array<int, int|float>>
     */
    public function cardSparklines(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $branchRows = AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(buy_volume_myr + sell_volume_myr) as volume')
            ->selectRaw('SUM(completed_count) as completed')
            ->selectRaw('SUM(tx_count) as tx_count')
            ->selectRaw('SUM(cancelled_count) as cancelled')
            ->selectRaw('SUM(approved_count) as approved')
            ->selectRaw('SUM(approval_seconds_total) as approval_seconds')
            ->selectRaw('SUM(unique_customers) as unique_customers')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $marginRows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(realized_margin_myr) as margin')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $series = [
            'volume_myr' => [],
            'realized_margin_myr' => [],
            'tx_count' => [],
            'unique_customers' => [],
            'avg_ticket_myr' => [],
            'cancel_rate_pct' => [],
            'avg_approval_seconds' => [],
        ];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $row = $branchRows->get($key);

            $volume = (string) ($row->volume ?? '0');
            $completed = (string) ($row->completed ?? '0');
            $txCount = (string) ($row->tx_count ?? '0');
            $approved = (string) ($row->approved ?? '0');

            $series['volume_myr'][] = (float) $volume;
            $marginRow = $marginRows->get($key);
            $series['realized_margin_myr'][] = (float) ($marginRow === null ? '0' : (string) $marginRow->margin);
            $series['tx_count'][] = (int) ($row->tx_count ?? 0);
            $series['unique_customers'][] = (int) ($row->unique_customers ?? 0);
            $series['avg_ticket_myr'][] = (float) $this->mathService->safeDivide($volume, $completed, 4);
            $series['cancel_rate_pct'][] = (float) $this->mathService->multiply(
                $this->mathService->safeDivide((string) ($row->cancelled ?? '0'), $txCount, 6),
                '100', 4
            );
            $series['avg_approval_seconds'][] = (float) $this->mathService->safeDivide(
                (string) ($row->approval_seconds ?? '0'), $approved, 2
            );
        }

        return $series;
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
     * and period-unique customers come from transactions because summing
     * daily uniques would double-count repeat visitors.
     *
     * @return array<int, array{branch_id: int|null, branch_name: string, tx_count: int, completed_count: int, cancelled_count: int, volume_myr: string, sell_volume_myr: string, realized_margin_myr: string, margin_pct: string, avg_ticket_myr: string, unique_customers: int}>
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

        $customers = Transaction::query()
            ->completed()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->groupBy('branch_id')
            ->selectRaw('branch_id')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->toBase()
            ->pluck('customers', 'branch_id');

        return AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->leftJoin('branches', 'branches.id', '=', 'analytics_daily_branch.branch_id')
            ->groupBy('analytics_daily_branch.branch_id', 'branches.name')
            ->selectRaw('analytics_daily_branch.branch_id')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('SUM(analytics_daily_branch.tx_count) as tx_count')
            ->selectRaw('SUM(analytics_daily_branch.completed_count) as completed_count')
            ->selectRaw('SUM(analytics_daily_branch.cancelled_count) as cancelled_count')
            ->selectRaw('SUM(analytics_daily_branch.buy_volume_myr + analytics_daily_branch.sell_volume_myr) as volume')
            ->selectRaw('SUM(analytics_daily_branch.sell_volume_myr) as sell_volume')
            ->orderByDesc('volume')
            ->toBase()
            ->get()
            ->map(function ($row) use ($margins, $customers) {
                $volume = (string) $row->volume;
                $sell = (string) $row->sell_volume;
                $margin = (string) ($margins[$row->branch_id] ?? '0');

                return [
                    'branch_id' => $row->branch_id,
                    'branch_name' => $row->branch_name ?? 'Unassigned',
                    'tx_count' => (int) $row->tx_count,
                    'completed_count' => (int) $row->completed_count,
                    'cancelled_count' => (int) $row->cancelled_count,
                    'volume_myr' => $volume,
                    'sell_volume_myr' => $sell,
                    'realized_margin_myr' => $margin,
                    'margin_pct' => $this->mathService->multiply(
                        $this->mathService->safeDivide($margin, $sell, 6),
                        '100', 4
                    ),
                    'avg_ticket_myr' => $this->mathService->safeDivide($volume, (string) $row->completed_count, 4),
                    'unique_customers' => (int) ($customers[$row->branch_id] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Per-currency deep-dive for the currency report: quantities, volumes,
     * realized margin and share of total period volume.
     *
     * @return array<int, array{currency_code: string, buy_qty: string, sell_qty: string, buy_volume_myr: string, sell_volume_myr: string, volume_myr: string, realized_margin_myr: string, share_pct: string}>
     */
    public function currencyBreakdown(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('currency_code')
            ->selectRaw('currency_code')
            ->selectRaw('SUM(buy_qty) as buy_qty')
            ->selectRaw('SUM(sell_qty) as sell_qty')
            ->selectRaw('SUM(buy_volume_myr) as buy_volume')
            ->selectRaw('SUM(sell_volume_myr) as sell_volume')
            ->selectRaw('SUM(buy_volume_myr + sell_volume_myr) as volume')
            ->selectRaw('SUM(realized_margin_myr) as margin')
            ->orderByDesc('volume')
            ->toBase()
            ->get();

        $total = $rows->reduce(
            fn ($carry, $row) => $this->mathService->add($carry, (string) $row->volume),
            '0'
        );

        return $rows->map(fn ($row) => [
            'currency_code' => $row->currency_code,
            'buy_qty' => (string) $row->buy_qty,
            'sell_qty' => (string) $row->sell_qty,
            'buy_volume_myr' => (string) $row->buy_volume,
            'sell_volume_myr' => (string) $row->sell_volume,
            'volume_myr' => (string) $row->volume,
            'realized_margin_myr' => (string) $row->margin,
            'share_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide((string) $row->volume, $total, 6),
                '100', 2
            ),
        ])->all();
    }

    /**
     * Daily series for a single currency — the deep-dive trend chart.
     *
     * @return array{labels: array<int, string>, buy: array<int, string>, sell: array<int, string>, margin: array<int, string>}
     */
    public function currencyTrend(Carbon $from, Carbon $to, ?int $branchId, string $currencyCode): array
    {
        $rows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->where('currency_code', $currencyCode)
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
            $row = $rows->get($day->toDateString());
            $labels[] = $day->format('M j');
            $buy[] = $row->buy_volume ?? '0';
            $sell[] = $row->sell_volume ?? '0';
            $margin[] = $row->margin ?? '0';
        }

        return compact('labels', 'buy', 'sell', 'margin');
    }

    /**
     * Rate analytics: daily spread capture and override frequency from the
     * rollups, plus the per-currency deviation of the transacted rate from
     * the market mid. Deviation reads the transaction rows because the
     * rollups never carried `base_rate`.
     *
     * @param  string|null  $currencyCode  Restrict the daily series to one currency.
     * @return array{labels: array<int, string>, spread: array<int, string>, overrides: array<int, int>, override_rate_pct: array<int, string>, currencies: array<int, array<string, mixed>>}
     */
    public function rateAnalytics(Carbon $from, Carbon $to, ?int $branchId, ?string $currencyCode = null): array
    {
        $spreadRows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->when($currencyCode, fn ($query) => $query->where('currency_code', $currencyCode))
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(avg_buy_rate * buy_volume_myr) as buy_value')
            ->selectRaw('SUM(buy_volume_myr) as buy_volume')
            ->selectRaw('SUM(avg_sell_rate * sell_volume_myr) as sell_value')
            ->selectRaw('SUM(sell_volume_myr) as sell_volume')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $overrideRows = AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(rate_override_count) as overrides')
            ->selectRaw('SUM(tx_count) as tx_count')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $labels = $spread = $overrides = $overrideRate = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $spreadRow = $spreadRows->get($key);
            $overrideRow = $overrideRows->get($key);

            $labels[] = $day->format('M j');
            $spread[] = $this->dailySpread($spreadRow);
            $overrides[] = (int) ($overrideRow->overrides ?? 0);
            $overrideRate[] = $overrideRow === null
                ? '0'
                : $this->mathService->multiply(
                    $this->mathService->safeDivide((string) $overrideRow->overrides, (string) $overrideRow->tx_count, 6),
                    '100', 4
                );
        }

        return [
            'labels' => $labels,
            'spread' => $spread,
            'overrides' => $overrides,
            'override_rate_pct' => $overrideRate,
            'currencies' => $this->rateDeviation($from, $to, $branchId),
        ];
    }

    /**
     * Volume-weighted buy/sell spread for one day's currency rollup rows.
     * Zero when either side had no volume — a spread across a one-sided
     * day is not meaningful.
     */
    protected function dailySpread(?\stdClass $row): string
    {
        if ($row === null) {
            return '0';
        }

        $buyVolume = (string) $row->buy_volume;
        $sellVolume = (string) $row->sell_volume;

        if ($this->mathService->compare($buyVolume, '0') <= 0
            || $this->mathService->compare($sellVolume, '0') <= 0) {
            return '0';
        }

        $avgBuy = $this->mathService->safeDivide((string) $row->buy_value, $buyVolume, 8);
        $avgSell = $this->mathService->safeDivide((string) $row->sell_value, $sellVolume, 8);

        return $this->mathService->subtract($avgSell, $avgBuy);
    }

    /**
     * Per-currency deviation of the transacted rate from the market mid
     * (`base_rate`) over completed transactions in the period.
     *
     * @return array<int, array{currency_code: string, tx_count: int, avg_rate: string, avg_base_rate: string, deviation_pct: string, override_count: int}>
     */
    protected function rateDeviation(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return Transaction::query()
            ->completed()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->forBranch($branchId)
            ->whereNotNull('base_rate')
            ->groupBy('currency_code')
            ->selectRaw('currency_code')
            ->selectRaw('COUNT(*) as tx_count')
            ->selectRaw('SUM(rate) as rate_total')
            ->selectRaw('SUM(base_rate) as base_total')
            ->selectRaw('SUM(CASE WHEN rate_override THEN 1 ELSE 0 END) as overrides')
            ->orderBy('currency_code')
            ->toBase()
            ->get()
            ->map(function ($row) {
                $txCount = (string) $row->tx_count;
                $avgRate = $this->mathService->safeDivide((string) $row->rate_total, $txCount, 8);
                $avgBase = $this->mathService->safeDivide((string) $row->base_total, $txCount, 8);

                return [
                    'currency_code' => $row->currency_code,
                    'tx_count' => (int) $row->tx_count,
                    'avg_rate' => $avgRate,
                    'avg_base_rate' => $avgBase,
                    'deviation_pct' => $this->mathService->multiply(
                        $this->mathService->safeDivide(
                            $this->mathService->subtract($avgRate, $avgBase),
                            $avgBase,
                            8
                        ),
                        '100', 4
                    ),
                    'override_count' => (int) $row->overrides,
                ];
            })
            ->all();
    }

    /**
     * Daily operational SLA series: average approval turnaround and
     * cancel/reject rates per day.
     *
     * @return array{labels: array<int, string>, avg_approval_seconds: array<int, string>, cancel_rate_pct: array<int, string>, reject_rate_pct: array<int, string>}
     */
    public function operationsTrend(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(tx_count) as tx_count')
            ->selectRaw('SUM(cancelled_count) as cancelled')
            ->selectRaw('SUM(rejected_count) as rejected')
            ->selectRaw('SUM(approved_count) as approved')
            ->selectRaw('SUM(approval_seconds_total) as approval_seconds')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $labels = $avgApproval = $cancelRate = $rejectRate = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $labels[] = $day->format('M j');
            $avgApproval[] = $row === null ? '0' : $this->mathService->safeDivide(
                (string) $row->approval_seconds, (string) $row->approved, 2
            );
            $cancelRate[] = $row === null ? '0' : $this->mathService->multiply(
                $this->mathService->safeDivide((string) $row->cancelled, (string) $row->tx_count, 6), '100', 4
            );
            $rejectRate[] = $row === null ? '0' : $this->mathService->multiply(
                $this->mathService->safeDivide((string) $row->rejected, (string) $row->tx_count, 6), '100', 4
            );
        }

        return [
            'labels' => $labels,
            'avg_approval_seconds' => $avgApproval,
            'cancel_rate_pct' => $cancelRate,
            'reject_rate_pct' => $rejectRate,
        ];
    }

    /**
     * Approval SLA depth: turnaround distribution, the live pending-approval
     * backlog bucketed by age, and per-approver workload. The distribution
     * and the backlog read the transactions table — the rollups carry only
     * the total seconds, which cannot yield percentiles or current state.
     *
     * @return array{median_seconds: string, p90_seconds: string, min_seconds: string, max_seconds: string, backlog: array<int, array{bucket: string, count: int}>, approvers: array<int, array{user_id: int, approver_name: string, approved_count: int}>}
     */
    public function approvalSla(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $seconds = Transaction::query()
            ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->whereNotNull('created_at')
            ->whereNotNull('approved_at')
            ->select('created_at', 'approved_at')
            ->get()
            ->map(fn ($row) => abs((int) $row->created_at->diffInSeconds($row->approved_at)))
            ->sort()
            ->values();

        $count = $seconds->count();

        $backlog = Transaction::query()
            ->pendingApproval()
            ->forBranch($branchId)
            ->whereNotNull('created_at')
            ->select('created_at')
            ->get()
            ->reduce(function (array $buckets, $row): array {
                $age = (int) now()->diffInHours($row->created_at);

                $key = match (true) {
                    $age < 1 => '0-1h',
                    $age < 4 => '1-4h',
                    $age < 24 => '4-24h',
                    default => 'over 24h',
                };

                $buckets[$key]++;

                return $buckets;
            }, ['0-1h' => 0, '1-4h' => 0, '4-24h' => 0, 'over 24h' => 0]);

        $approvers = Transaction::query()
            ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->whereNotNull('approved_by')
            ->groupBy('approved_by')
            ->selectRaw('approved_by as user_id')
            ->selectRaw('COUNT(*) as approved_count')
            ->orderByDesc('approved_count')
            ->limit(20)
            ->toBase()
            ->get();

        $names = User::query()->whereIn('id', $approvers->pluck('user_id'))->pluck('username', 'id');

        return [
            'median_seconds' => $this->percentile($seconds, 50),
            'p90_seconds' => $this->percentile($seconds, 90),
            'min_seconds' => $count > 0 ? (string) $seconds->first() : '0',
            'max_seconds' => $count > 0 ? (string) $seconds->last() : '0',
            'backlog' => collect($backlog)
                ->map(fn (int $bucketCount, string $bucket) => ['bucket' => $bucket, 'count' => $bucketCount])
                ->values()
                ->all(),
            'approvers' => $approvers->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'approver_name' => $names->get($row->user_id) ?? 'Unknown',
                'approved_count' => (int) $row->approved_count,
            ])->all(),
        ];
    }

    /**
     * Nearest-rank percentile over a sorted collection of integers.
     *
     * @param  Collection<int, int<0, max>>  $sorted
     */
    protected function percentile(Collection $sorted, int $percentile): string
    {
        $count = $sorted->count();

        if ($count === 0) {
            return '0';
        }

        $index = max(0, min($count - 1, (int) ceil($percentile / 100 * $count) - 1));

        return (string) $sorted->get($index);
    }

    /**
     * Stock and risk: position exposure and limit utilization, turnover
     * and days-of-stock from the period's currency rollup, plus the
     * compliance snapshot the plan lists as a read-only BI view.
     *
     * @return array{positions: array<int, array<string, mixed>>, totals: array<string, string>, compliance: array<string, int|string>}
     */
    public function stockAndCompliance(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $limits = $this->thresholdService->getPositionLimits();
        $days = (string) ((int) $from->diffInDays($to) + 1);

        $volumeRows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->forBranch($branchId)
            ->groupBy('currency_code')
            ->selectRaw('currency_code')
            ->selectRaw('SUM(sell_qty) as sold')
            ->selectRaw('SUM(buy_volume_myr + sell_volume_myr) as volume')
            ->toBase()
            ->get()
            ->keyBy('currency_code');

        $positions = CurrencyPosition::query()
            ->with('currency')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('currency_code')
            ->get();

        $rows = [];
        $totalExposure = '0';
        $totalUnrealized = '0';

        foreach ($positions as $position) {
            $quantity = $this->mathService->abs((string) $position->quantity);
            $rate = (string) ($position->current_rate ?? '0');
            $limit = $limits[$position->currency_code] ?? null;
            $limitValue = $limit ?? '0';

            $volumeRow = $volumeRows->get($position->currency_code);
            $sold = $volumeRow === null ? '0' : (string) $volumeRow->sold;
            $volume = $volumeRow === null ? '0' : (string) $volumeRow->volume;

            $rows[] = [
                'currency_code' => $position->currency_code,
                'currency_name' => $position->currency->name ?? $position->currency_code,
                'quantity' => (string) $position->quantity,
                'average_cost' => (string) $position->average_cost,
                'current_rate' => $rate,
                'current_value' => (string) $position->current_value,
                'unrealized_gain_loss' => (string) $position->unrealized_gain_loss,
                'position_limit' => $limit,
                'utilization_pct' => $this->mathService->compare($limitValue, '0') > 0
                    ? $this->mathService->multiply($this->mathService->divide($quantity, $limitValue), '100')
                    : '0',
                'turnover_ratio' => $this->mathService->safeDivide($volume, $quantity, 2),
                'days_of_stock' => $this->mathService->compare($sold, '0') > 0
                    ? $this->mathService->divide($quantity, $this->mathService->divide($sold, $days, 4), 1)
                    : '0',
            ];

            $totalExposure = $this->mathService->add(
                $totalExposure,
                $this->mathService->multiply($quantity, $rate)
            );
            $totalUnrealized = $this->mathService->add($totalUnrealized, (string) $position->unrealized_gain_loss);
        }

        $completed = Transaction::query()
            ->completed()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->forBranch($branchId)
            ->count();

        $flagged = FlaggedTransaction::query()
            ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
            ->count();

        return [
            'positions' => $rows,
            'totals' => [
                'exposure_myr' => $totalExposure,
                'unrealized_gain_loss_myr' => $totalUnrealized,
                'unrealized_pct' => $this->mathService->multiply(
                    $this->mathService->safeDivide($totalUnrealized, $totalExposure, 8),
                    '100', 2
                ),
            ],
            'compliance' => [
                'completed_transactions' => $completed,
                'flagged_transactions' => $flagged,
                'flagged_rate_pct' => $this->mathService->multiply(
                    $this->mathService->safeDivide((string) $flagged, (string) $completed, 6),
                    '100', 4
                ),
                'open_alerts' => Alert::query()->open()->count(),
                'open_flags' => FlaggedTransaction::query()->open()->count(),
                'str_reports' => StrReport::query()->count(),
            ],
        ];
    }

    /**
     * Per-teller productivity over the period — volume, outcomes, rate
     * overrides, and average approval turnaround of their own work.
     *
     * @return array<int, array{user_id: int, teller_name: string, tx_count: int, volume_myr: string, cancelled_count: int, rejected_count: int, rate_override_count: int, avg_approval_seconds: string}>
     */
    public function tellerProductivity(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return AnalyticsDailyTeller::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            // Qualified: the users join also carries a branch_id column.
            ->when($branchId, fn ($query) => $query->where('analytics_daily_teller.branch_id', $branchId))
            ->leftJoin('users', 'users.id', '=', 'analytics_daily_teller.user_id')
            ->groupBy('analytics_daily_teller.user_id', 'users.username')
            ->selectRaw('analytics_daily_teller.user_id')
            ->selectRaw('users.username as teller_name')
            ->selectRaw('SUM(analytics_daily_teller.tx_count) as tx_count')
            ->selectRaw('SUM(analytics_daily_teller.volume_myr) as volume')
            ->selectRaw('SUM(analytics_daily_teller.cancelled_count) as cancelled')
            ->selectRaw('SUM(analytics_daily_teller.rejected_count) as rejected')
            ->selectRaw('SUM(analytics_daily_teller.rate_override_count) as overrides')
            ->selectRaw('SUM(analytics_daily_teller.approved_count) as approved')
            ->selectRaw('SUM(analytics_daily_teller.approval_seconds_total) as approval_seconds')
            ->orderByDesc('volume')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'teller_name' => $row->teller_name ?? 'Unknown',
                'tx_count' => (int) $row->tx_count,
                'volume_myr' => (string) $row->volume,
                'cancelled_count' => (int) $row->cancelled,
                'rejected_count' => (int) $row->rejected,
                'rate_override_count' => (int) $row->overrides,
                'avg_approval_seconds' => $this->mathService->safeDivide(
                    (string) $row->approval_seconds, (string) $row->approved, 2
                ),
            ])
            ->all();
    }

    /**
     * Top customers by period volume from the monthly-customer rollup.
     *
     * @return array<int, array{customer_id: int, full_name: string, tx_count: int, volume_myr: string, realized_margin_myr: string, currencies_used: int}>
     */
    public function topCustomers(Carbon $from, Carbon $to, ?int $branchId, int $limit = 20): array
    {
        return AnalyticsMonthlyCustomer::query()
            ->whereBetween('month', [$from->format('Y-m'), $to->format('Y-m')])
            ->forBranch($branchId)
            ->leftJoin('customers', 'customers.id', '=', 'analytics_monthly_customer.customer_id')
            ->groupBy('analytics_monthly_customer.customer_id', 'customers.full_name')
            ->selectRaw('analytics_monthly_customer.customer_id')
            ->selectRaw('customers.full_name')
            ->selectRaw('SUM(analytics_monthly_customer.tx_count) as tx_count')
            ->selectRaw('SUM(analytics_monthly_customer.volume_myr) as volume')
            ->selectRaw('SUM(analytics_monthly_customer.realized_margin_myr) as margin')
            ->selectRaw('MAX(analytics_monthly_customer.currencies_used) as currencies_used')
            ->orderByDesc('volume')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'full_name' => $row->full_name ?? 'Unknown',
                'tx_count' => (int) $row->tx_count,
                'volume_myr' => (string) $row->volume,
                'realized_margin_myr' => (string) $row->margin,
                'currencies_used' => (int) $row->currencies_used,
            ])
            ->all();
    }

    /**
     * Monthly active-customer counts split into new vs returning. A
     * customer counts as "new" in the month of their first-ever rollup
     * row; months in the period with no activity are zero-filled.
     *
     * @return array{labels: array<int, string>, active: array<int, int>, new: array<int, int>}
     */
    public function activeCustomersTrend(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $monthFrom = $from->format('Y-m');
        $monthTo = $to->format('Y-m');

        $active = AnalyticsMonthlyCustomer::query()
            ->whereBetween('month', [$monthFrom, $monthTo])
            ->forBranch($branchId)
            ->groupBy('month')
            ->selectRaw('month')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->toBase()
            ->pluck('customers', 'month');

        // First month each customer ever appears — over all history, not
        // just the selected window, so a customer who started last year
        // is not mislabelled "new" in this period.
        $firstMonths = DB::table('analytics_monthly_customer')
            ->select('customer_id', DB::raw('MIN(month) as first_month'))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('customer_id');

        $new = DB::query()
            ->fromSub($firstMonths, 'fm')
            ->selectRaw('first_month as month, COUNT(*) as new_customers')
            ->whereBetween('first_month', [$monthFrom, $monthTo])
            ->groupBy('first_month')
            ->pluck('new_customers', 'month');

        $labels = $activeSeries = $newSeries = [];
        for ($month = Carbon::parse($monthFrom.'-01'); $month->format('Y-m') <= $monthTo; $month->addMonth()) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $activeSeries[] = (int) ($active[$key] ?? 0);
            $newSeries[] = (int) ($new[$key] ?? 0);
        }

        return ['labels' => $labels, 'active' => $activeSeries, 'new' => $newSeries];
    }

    /**
     * Customer analytics beyond the top-accounts table: concentration,
     * repeat rate, lifetime value, and a cohort retention matrix.
     *
     * @return array{concentration_top10_pct: string, repeat_rate_pct: string, avg_txn_per_active: string, avg_ltv_myr: string, cohorts: array<int, array<string, mixed>>}
     */
    public function customerAnalytics(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $monthFrom = $from->format('Y-m');
        $monthTo = $to->format('Y-m');

        $perCustomer = AnalyticsMonthlyCustomer::query()
            ->whereBetween('month', [$monthFrom, $monthTo])
            ->forBranch($branchId)
            ->selectRaw('customer_id')
            ->selectRaw('SUM(tx_count) as tx_count')
            ->selectRaw('SUM(volume_myr) as volume')
            ->groupBy('customer_id')
            ->toBase()
            ->get();

        $totalVolume = $perCustomer->reduce(
            fn ($carry, $row) => $this->mathService->add($carry, (string) $row->volume),
            '0'
        );

        $top10Volume = (string) $perCustomer
            ->sortByDesc(fn ($row) => (float) $row->volume)
            ->take(10)
            ->reduce(fn ($carry, $row) => $this->mathService->add($carry, (string) $row->volume), '0');

        $activeCount = $perCustomer->count();
        $repeatCount = $perCustomer->filter(fn ($row) => (int) $row->tx_count > 1)->count();

        return [
            'concentration_top10_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide($top10Volume, $totalVolume, 6),
                '100', 2
            ),
            'repeat_rate_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide((string) $repeatCount, (string) $activeCount, 6),
                '100', 2
            ),
            'avg_txn_per_active' => $this->mathService->safeDivide(
                (string) $perCustomer->sum('tx_count'), (string) $activeCount, 2
            ),
            'avg_ltv_myr' => $this->mathService->safeDivide($totalVolume, (string) $activeCount, 2),
            'cohorts' => $this->customerCohorts($monthFrom, $monthTo, $branchId),
        ];
    }

    /**
     * Cohort retention: customers grouped by the month of their first-ever
     * rollup row, then counted active in each subsequent month. Only the
     * cohort months inside the window are reported, so a customer who
     * started earlier counts as returning, never as "new".
     *
     * @return array<int, array{cohort_month: string, cohort_size: int, cells: array<int, array{month: string, active: int, pct: string}>}>
     */
    protected function customerCohorts(string $monthFrom, string $monthTo, ?int $branchId): array
    {
        $firstMonths = DB::table('analytics_monthly_customer')
            ->select('customer_id', DB::raw('MIN(month) as first_month'))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('customer_id');

        $cohortSizes = DB::query()
            ->fromSub($firstMonths, 'fm')
            ->selectRaw('first_month as cohort_month')
            ->selectRaw('COUNT(*) as size')
            ->whereBetween('first_month', [$monthFrom, $monthTo])
            ->groupBy('first_month')
            ->pluck('size', 'cohort_month');

        if ($cohortSizes->isEmpty()) {
            return [];
        }

        $retention = DB::query()
            ->fromSub($firstMonths, 'fm')
            ->join('analytics_monthly_customer as amc', function ($join): void {
                $join->on('amc.customer_id', '=', 'fm.customer_id')
                    ->on('amc.month', '>=', 'fm.first_month');
            })
            ->whereBetween('fm.first_month', [$monthFrom, $monthTo])
            ->whereBetween('amc.month', [$monthFrom, $monthTo])
            ->when($branchId, fn ($query) => $query->where('amc.branch_id', $branchId))
            ->selectRaw('fm.first_month as cohort_month')
            ->selectRaw('amc.month as month')
            ->selectRaw('COUNT(DISTINCT fm.customer_id) as active')
            ->groupBy('fm.first_month', 'amc.month')
            ->get()
            ->groupBy('cohort_month');

        $months = [];
        for ($month = Carbon::parse($monthFrom.'-01'); $month->format('Y-m') <= $monthTo; $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        $retentionByMonth = $retention->map(
            fn ($group) => $group->keyBy('month')
        );

        return $cohortSizes->map(function ($size, $cohortMonth) use ($months, $retentionByMonth): array {
            $cells = [];

            foreach ($months as $month) {
                if ($month < $cohortMonth) {
                    continue;
                }

                $match = $retentionByMonth->get($cohortMonth)?->get($month);
                $active = $match === null ? 0 : (int) $match->active;

                $cells[] = [
                    'month' => $month,
                    'active' => $active,
                    'pct' => $this->mathService->multiply(
                        $this->mathService->safeDivide((string) $active, (string) $size, 6),
                        '100', 1
                    ),
                ];
            }

            return ['cohort_month' => $cohortMonth, 'cohort_size' => (int) $size, 'cells' => $cells];
        })->values()->all();
    }

    /**
     * Public scalar metrics map for one period — the single source behind
     * KPI cards, BI exports and alert-rule evaluation. Keys:
     * volume_myr, tx_count, avg_ticket_myr, realized_margin_myr,
     * margin_pct, cancel_rate_pct, reject_rate_pct,
     * avg_approval_seconds, rate_override_count, unique_customers.
     *
     * @return array<string, string|int>
     */
    public function periodMetrics(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $metrics = $this->summarize($from, $to, $branchId);
        $metrics['unique_customers'] = $this->distinctCustomers($from, $to, $branchId);

        return $metrics;
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
            ->selectRaw('SUM(rejected_count) as rejected_count')
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
        $rejectedCount = (int) ($row->rejected_count ?? 0);
        $approvedCount = (int) ($row->approved_count ?? 0);
        $approvalSeconds = (string) ($row->approval_seconds_total ?? '0');

        $margin = $this->totalMargin($from, $to, $branchId);

        return [
            'volume_myr' => $volume,
            'net_volume_myr' => $this->mathService->subtract($buy, $sell),
            'buy_sell_ratio' => $this->mathService->safeDivide($buy, $sell, 4),
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
            'reject_rate_pct' => $this->mathService->multiply(
                $this->mathService->safeDivide((string) $rejectedCount, (string) $txCount, 6),
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
