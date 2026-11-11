<?php

namespace App\Services\Bi;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Support\DbDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the BI rollup tables (analytics_*) from the transactions table.
 *
 * Rebuilds are delete-then-insert inside a transaction keyed on the
 * rollup's natural dimension (date / month) — idempotent by construction,
 * self-healing on late cancellations/reversals, and safe to re-run over
 * any range. Unique indexes on the rollups are the backstop against
 * racing builders.
 */
class AnalyticsSnapshotBuilder
{
    /**
     * Rebuild every day in [from, to] plus each touched month once.
     */
    public function rebuildRange(Carbon $from, Carbon $to): void
    {
        $months = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $this->rebuildDaily($day);
            $months[$day->format('Y-m')] = true;
        }

        foreach (array_keys($months) as $month) {
            $this->rebuildMonth($month);
        }
    }

    /**
     * Rebuild one day of daily rollups and the month containing it.
     */
    public function rebuildDay(Carbon $day): void
    {
        $this->rebuildDaily($day);
        $this->rebuildMonth($day->format('Y-m'));
    }

    /**
     * Rebuild only the daily rollups for today — the cheap freshness path
     * for dashboards (3 aggregate queries). Callers should throttle via
     * `Cache::add` debounce; the monthly rollup is intentionally left to
     * the nightly window rebuild, so month-grain analytics lag by design.
     */
    public function refreshToday(): void
    {
        $this->rebuildDaily(today());
    }

    /**
     * Rebuild one month of the monthly-customer rollup.
     *
     * @param  string  $month  'YYYY-MM'
     */
    public function rebuildMonth(string $month): void
    {
        $start = Carbon::parse("{$month}-01")->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $bucket = DbDate::monthBucket('created_at');
        $completed = TransactionStatus::Completed->value;
        $sell = TransactionType::Sell->value;
        $now = now();

        $rows = DB::table('transactions')
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('deleted_at')
            ->where('status', $completed)
            ->groupBy(DB::raw($bucket), 'customer_id', 'branch_id')
            ->selectRaw("{$bucket} as month")
            ->selectRaw('customer_id')
            ->selectRaw('branch_id')
            ->selectRaw('COUNT(*) as tx_count')
            ->selectRaw('SUM(amount_myr) as volume_myr')
            ->selectRaw('SUM(CASE WHEN type = ? AND prev_average_cost IS NOT NULL THEN (rate - prev_average_cost) * quantity ELSE 0 END) as realized_margin_myr', [$sell])
            ->selectRaw('COUNT(DISTINCT currency_code) as currencies_used')
            ->get()
            ->map(fn ($row) => [
                'month' => $month,
                'customer_id' => $row->customer_id,
                'branch_id' => $row->branch_id,
                'tx_count' => $row->tx_count,
                'volume_myr' => $row->volume_myr,
                'realized_margin_myr' => $row->realized_margin_myr,
                'currencies_used' => $row->currencies_used,
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::transaction(function () use ($month, $rows) {
            DB::table('analytics_monthly_customer')->where('month', $month)->delete();

            if ($rows !== []) {
                DB::table('analytics_monthly_customer')->insert($rows);
            }
        });
    }

    /**
     * Rebuild the three daily rollups for a single day.
     */
    protected function rebuildDaily(Carbon $day): void
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $date = $day->toDateString();
        $now = now();

        $branchRows = $this->aggregateBranchDay($from, $to, $date, $now);
        $currencyRows = $this->aggregateCurrencyDay($from, $to, $date, $now);
        $tellerRows = $this->aggregateTellerDay($from, $to, $date, $now);

        DB::transaction(function () use ($date, $branchRows, $currencyRows, $tellerRows) {
            DB::table('analytics_daily_branch')->where('date', $date)->delete();
            DB::table('analytics_daily_currency')->where('date', $date)->delete();
            DB::table('analytics_daily_teller')->where('date', $date)->delete();

            foreach ([
                'analytics_daily_branch' => $branchRows,
                'analytics_daily_currency' => $currencyRows,
                'analytics_daily_teller' => $tellerRows,
            ] as $table => $rows) {
                if ($rows !== []) {
                    DB::table($table)->insert($rows);
                }
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function aggregateBranchDay(Carbon $from, Carbon $to, string $date, Carbon $now): array
    {
        $completed = TransactionStatus::Completed->value;
        $rejected = TransactionStatus::Rejected->value;
        $cancelled = TransactionStatus::Cancelled->value;
        $buy = TransactionType::Buy->value;
        $sell = TransactionType::Sell->value;
        $approvalSeconds = DbDate::secondsBetween('created_at', 'approved_at');

        return DB::table('transactions')
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->groupBy('branch_id')
            ->selectRaw('branch_id')
            ->selectRaw('COUNT(*) as tx_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count', [$rejected])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count', [$cancelled])
            ->selectRaw('SUM(CASE WHEN approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved_count')
            ->selectRaw("SUM(CASE WHEN approved_at IS NOT NULL AND created_at IS NOT NULL THEN {$approvalSeconds} ELSE 0 END) as approval_seconds_total")
            ->selectRaw('SUM(CASE WHEN rate_override = 1 THEN 1 ELSE 0 END) as rate_override_count')
            ->selectRaw('SUM(CASE WHEN status = ? AND type = ? THEN amount_myr ELSE 0 END) as buy_volume_myr', [$completed, $buy])
            ->selectRaw('SUM(CASE WHEN status = ? AND type = ? THEN amount_myr ELSE 0 END) as sell_volume_myr', [$completed, $sell])
            ->selectRaw('COUNT(DISTINCT CASE WHEN status = ? THEN customer_id END) as unique_customers', [$completed])
            ->get()
            ->map(function ($row) use ($date, $now) {
                $buyVolume = is_numeric($row->buy_volume_myr) ? (string) $row->buy_volume_myr : '0';
                $sellVolume = is_numeric($row->sell_volume_myr) ? (string) $row->sell_volume_myr : '0';

                return [
                    'date' => $date,
                    'branch_id' => $row->branch_id,
                    'tx_count' => $row->tx_count,
                    'completed_count' => $row->completed_count,
                    'rejected_count' => $row->rejected_count,
                    'cancelled_count' => $row->cancelled_count,
                    'approved_count' => $row->approved_count,
                    'approval_seconds_total' => $row->approval_seconds_total,
                    'rate_override_count' => $row->rate_override_count,
                    'buy_volume_myr' => $row->buy_volume_myr,
                    'sell_volume_myr' => $row->sell_volume_myr,
                    'net_volume_myr' => bcsub($buyVolume, $sellVolume, 4),
                    'unique_customers' => $row->unique_customers,
                    'computed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function aggregateCurrencyDay(Carbon $from, Carbon $to, string $date, Carbon $now): array
    {
        $completed = TransactionStatus::Completed->value;
        $buy = TransactionType::Buy->value;
        $sell = TransactionType::Sell->value;

        return DB::table('transactions')
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->where('status', $completed)
            ->groupBy('branch_id', 'currency_code')
            ->selectRaw('branch_id')
            ->selectRaw('currency_code')
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as buy_qty', [$buy])
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as sell_qty', [$sell])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount_myr ELSE 0 END) as buy_volume_myr', [$buy])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount_myr ELSE 0 END) as sell_volume_myr', [$sell])
            ->selectRaw('SUM(CASE WHEN type = ? AND prev_average_cost IS NOT NULL THEN (rate - prev_average_cost) * quantity ELSE 0 END) as realized_margin_myr', [$sell])
            ->get()
            ->map(function ($row) use ($date, $now) {
                $buyQty = is_numeric($row->buy_qty) ? (string) $row->buy_qty : '0';
                $sellQty = is_numeric($row->sell_qty) ? (string) $row->sell_qty : '0';
                $buyVolume = is_numeric($row->buy_volume_myr) ? (string) $row->buy_volume_myr : '0';
                $sellVolume = is_numeric($row->sell_volume_myr) ? (string) $row->sell_volume_myr : '0';

                return [
                    'date' => $date,
                    'branch_id' => $row->branch_id,
                    'currency_code' => $row->currency_code,
                    'buy_qty' => $row->buy_qty,
                    'sell_qty' => $row->sell_qty,
                    'buy_volume_myr' => $row->buy_volume_myr,
                    'sell_volume_myr' => $row->sell_volume_myr,
                    'realized_margin_myr' => $row->realized_margin_myr,
                    'avg_buy_rate' => bccomp($buyQty, '0', 8) > 0 ? bcdiv($buyVolume, $buyQty, 8) : '0',
                    'avg_sell_rate' => bccomp($sellQty, '0', 8) > 0 ? bcdiv($sellVolume, $sellQty, 8) : '0',
                    'computed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function aggregateTellerDay(Carbon $from, Carbon $to, string $date, Carbon $now): array
    {
        $completed = TransactionStatus::Completed->value;
        $rejected = TransactionStatus::Rejected->value;
        $cancelled = TransactionStatus::Cancelled->value;
        $approvalSeconds = DbDate::secondsBetween('created_at', 'approved_at');

        return DB::table('transactions')
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->groupBy('user_id')
            ->selectRaw('user_id')
            ->selectRaw('MAX(branch_id) as branch_id')
            ->selectRaw('COUNT(*) as tx_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN amount_myr ELSE 0 END) as volume_myr', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count', [$cancelled])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count', [$rejected])
            ->selectRaw('SUM(CASE WHEN rate_override = 1 THEN 1 ELSE 0 END) as rate_override_count')
            ->selectRaw('SUM(CASE WHEN approved_at IS NOT NULL THEN 1 ELSE 0 END) as approved_count')
            ->selectRaw("SUM(CASE WHEN approved_at IS NOT NULL AND created_at IS NOT NULL THEN {$approvalSeconds} ELSE 0 END) as approval_seconds_total")
            ->get()
            ->map(fn ($row) => [
                'date' => $date,
                'user_id' => $row->user_id,
                'branch_id' => $row->branch_id,
                'tx_count' => $row->tx_count,
                'volume_myr' => $row->volume_myr,
                'cancelled_count' => $row->cancelled_count,
                'rejected_count' => $row->rejected_count,
                'rate_override_count' => $row->rate_override_count,
                'approved_count' => $row->approved_count,
                'approval_seconds_total' => $row->approval_seconds_total,
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();
    }
}
