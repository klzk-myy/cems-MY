<?php

namespace App\Services\Bi;

use App\Models\Bi\AnalyticsDailyCurrency;
use App\Models\Bi\AnalyticsDailyForecast;
use App\Services\System\MathService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Currency demand forecasting over the analytics_daily_currency rollups.
 *
 * Method — "seasonal blend": predicted demand for a target day is a
 * 50/50 blend of
 *   - the day-of-week seasonal mean (the last SEASONAL_WEEKS occurrences
 *     of that weekday, absent occurrences counting as zero), and
 *   - the trailing mean over the last TRAILING_DAYS observed days.
 *
 * Deliberately explainable — stocking staff can see *why* a Monday
 * spikes. All arithmetic is BCMath via MathService; predictions are
 * rebuilt idempotently (delete-then-insert per forecast date).
 */
class DemandForecaster
{
    public const METHOD = 'seasonal_blend';

    public const TRAILING_DAYS = 14;

    public const SEASONAL_WEEKS = 4;

    public const LOOKBACK_DAYS = 28;

    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * (Re)build forecast rows for [startDate, startDate + horizon-1].
     * Writes per-branch predictions plus a consolidated branch_id NULL
     * row per currency (sum of branch predictions — means are linear).
     *
     * @return int rows written
     */
    public function generate(Carbon $startDate, int $horizonDays = 7): int
    {
        $lookbackFrom = $startDate->copy()->subDays(self::LOOKBACK_DAYS);
        $lookbackTo = $startDate->copy()->subDay();
        $trailingFrom = $startDate->copy()->subDays(self::TRAILING_DAYS);

        // Full lookback history keyed by branch|currency|date — one query,
        // all aggregation in PHP (the window is small).
        $history = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$lookbackFrom->toDateString(), $lookbackTo->toDateString()])
            ->whereNotNull('branch_id')
            ->get(['date', 'branch_id', 'currency_code', 'buy_qty', 'sell_qty', 'buy_volume_myr', 'sell_volume_myr']);

        /** @var array<string, array<string, array<string, string>>> $byCombo */
        $byCombo = [];
        $combos = [];
        foreach ($history as $row) {
            $key = $row->branch_id.'|'.$row->currency_code;
            $combos[$key] ??= ['branch_id' => (int) $row->branch_id, 'currency_code' => $row->currency_code, 'days' => 0];
            $combos[$key]['days']++;
            $byCombo[$key][$row->date->toDateString()] = [
                'buy_qty' => (string) $row->buy_qty,
                'sell_qty' => (string) $row->sell_qty,
                'buy_volume_myr' => (string) $row->buy_volume_myr,
                'sell_volume_myr' => (string) $row->sell_volume_myr,
            ];
        }

        $consolidated = [];
        /** @var array<int, array<string, mixed>> $insertRows */
        $insertRows = [];

        foreach ($combos as $key => $combo) {
            $days = $byCombo[$key] ?? [];

            for ($i = 0; $i < $horizonDays; $i++) {
                $target = $startDate->copy()->addDays($i);
                $row = $this->predict($days, $target, $trailingFrom, $lookbackTo) + [
                    'date' => $target->toDateString(),
                    'branch_id' => $combo['branch_id'],
                    'currency_code' => $combo['currency_code'],
                    'method' => self::METHOD,
                    'sample_days' => $combo['days'],
                ];

                $insertRows[] = $row;

                $cKey = $target->toDateString().'|'.$combo['currency_code'];
                $consolidated[$cKey] ??= [
                    'date' => $target->toDateString(),
                    'branch_id' => null,
                    'currency_code' => $combo['currency_code'],
                    'method' => self::METHOD,
                    'sample_days' => $combo['days'],
                    'predicted_buy_qty' => '0',
                    'predicted_sell_qty' => '0',
                    'predicted_buy_volume_myr' => '0',
                    'predicted_sell_volume_myr' => '0',
                ];
                foreach (['predicted_buy_qty', 'predicted_sell_qty', 'predicted_buy_volume_myr', 'predicted_sell_volume_myr'] as $col) {
                    $consolidated[$cKey][$col] = $this->mathService->add($consolidated[$cKey][$col], $row[$col]);
                    $consolidated[$cKey]['sample_days'] = max(
                        $consolidated[$cKey]['sample_days'],
                        $combo['days']
                    );
                }
            }
        }

        foreach ($consolidated as $row) {
            $insertRows[] = $row;
        }

        // Raw inserts like AnalyticsSnapshotBuilder — the model's 'date'
        // cast would serialize Y-m-d H:i:s, breaking the rollup's Y-m-d
        // string convention used by every analytics query.
        DB::transaction(function () use ($startDate, $horizonDays, $insertRows) {
            DB::table('analytics_daily_forecast')
                ->whereBetween('date', [
                    $startDate->toDateString(),
                    $startDate->copy()->addDays($horizonDays - 1)->toDateString(),
                ])
                ->delete();

            $now = now();
            foreach (array_chunk($insertRows, 200) as $chunk) {
                DB::table('analytics_daily_forecast')->insert(
                    array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk)
                );
            }
        });

        return count($insertRows);
    }

    /**
     * Forecast rows for the read path — per-currency or per-currency-all-branch.
     *
     * @return Collection<int, AnalyticsDailyForecast>
     */
    public function forecastFor(?int $branchId, string $currencyCode, Carbon $from, int $days): Collection
    {
        return AnalyticsDailyForecast::query()
            ->forBranch($branchId)
            ->where('currency_code', $currencyCode)
            ->whereBetween('date', [$from->toDateString(), $from->copy()->addDays($days - 1)->toDateString()])
            ->orderBy('date')
            ->get();
    }

    /**
     * Per-currency totals over the forecast horizon — the all-currency
     * stocking summary on the forecast page.
     *
     * @return array<int, array{currency_code: string, buy_qty: string, sell_qty: string, buy_volume_myr: string, sell_volume_myr: string, sample_days: int}>
     */
    public function forecastSummary(?int $branchId, Carbon $from, int $days): array
    {
        return AnalyticsDailyForecast::query()
            ->forBranch($branchId)
            ->whereBetween('date', [$from->toDateString(), $from->copy()->addDays($days - 1)->toDateString()])
            ->groupBy('currency_code')
            ->selectRaw('currency_code')
            ->selectRaw('SUM(predicted_buy_qty) as buy_qty')
            ->selectRaw('SUM(predicted_sell_qty) as sell_qty')
            ->selectRaw('SUM(predicted_buy_volume_myr) as buy_volume_myr')
            ->selectRaw('SUM(predicted_sell_volume_myr) as sell_volume_myr')
            ->selectRaw('MAX(sample_days) as sample_days')
            ->orderByDesc('buy_volume_myr')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'currency_code' => $row->currency_code,
                'buy_qty' => (string) $row->buy_qty,
                'sell_qty' => (string) $row->sell_qty,
                'buy_volume_myr' => (string) $row->buy_volume_myr,
                'sell_volume_myr' => (string) $row->sell_volume_myr,
                'sample_days' => (int) $row->sample_days,
            ])
            ->all();
    }

    /**
     * Actual daily history for a currency — the solid part of the chart.
     * Null branch = consolidated across branches (rollups have no
     * consolidated rows, so sum per date).
     *
     * @return array{labels: array<int, string>, buy: array<int, string>, sell: array<int, string>}
     */
    public function history(?int $branchId, string $currencyCode, Carbon $to, int $days): array
    {
        $from = $to->copy()->subDays($days - 1);

        $rows = AnalyticsDailyCurrency::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('currency_code', $currencyCode)
            ->groupBy('date')
            ->selectRaw('date')
            ->selectRaw('SUM(buy_qty) as buy_qty')
            ->selectRaw('SUM(sell_qty) as sell_qty')
            ->toBase()
            ->get()
            ->keyBy('date');

        $labels = $buy = $sell = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $key;
            $buy[] = (string) ($rows[$key]->buy_qty ?? '0');
            $sell[] = (string) ($rows[$key]->sell_qty ?? '0');
        }

        return ['labels' => $labels, 'buy' => $buy, 'sell' => $sell];
    }

    /**
     * Blend the weekday-seasonal mean and the trailing mean for one
     * (combo, target-day) pair. Every field of the day map is a
     * numeric-string; absent days count as zero.
     *
     * @param  array<string, array<string, string>>  $days  date => rollup values
     * @return array{predicted_buy_qty: string, predicted_sell_qty: string, predicted_buy_volume_myr: string, predicted_sell_volume_myr: string}
     */
    protected function predict(array $days, Carbon $target, Carbon $trailingFrom, Carbon $lookbackTo): array
    {
        return [
            'predicted_buy_qty' => $this->blend($days, 'buy_qty', $target, $trailingFrom, $lookbackTo),
            'predicted_sell_qty' => $this->blend($days, 'sell_qty', $target, $trailingFrom, $lookbackTo),
            'predicted_buy_volume_myr' => $this->blend($days, 'buy_volume_myr', $target, $trailingFrom, $lookbackTo),
            'predicted_sell_volume_myr' => $this->blend($days, 'sell_volume_myr', $target, $trailingFrom, $lookbackTo),
        ];
    }

    /**
     * predicted = 0.5 * seasonal_mean + 0.5 * trailing_mean.
     * Seasonal occurrences are always historical for a 7-day horizon
     * (target-28 >= lookback start); the trailing window stays fixed on
     * the last observed fortnight regardless of how far out the target
     * day is.
     *
     * @param  array<string, array<string, string>>  $days  date => rollup values
     */
    protected function blend(array $days, string $column, Carbon $target, Carbon $trailingFrom, Carbon $lookbackTo): string
    {
        $seasonalSum = '0';
        for ($w = 1; $w <= self::SEASONAL_WEEKS; $w++) {
            $seasonalSum = $this->mathService->add(
                $seasonalSum,
                $days[$target->copy()->subWeeks($w)->toDateString()][$column] ?? '0'
            );
        }
        $seasonalMean = $this->mathService->safeDivide($seasonalSum, (string) self::SEASONAL_WEEKS, 6);

        $trailingSum = '0';
        for ($d = $trailingFrom->copy(); $d->lte($lookbackTo); $d->addDay()) {
            $trailingSum = $this->mathService->add($trailingSum, $days[$d->toDateString()][$column] ?? '0');
        }
        $trailingMean = $this->mathService->safeDivide($trailingSum, (string) self::TRAILING_DAYS, 6);

        return $this->mathService->add(
            $this->mathService->multiply($seasonalMean, '0.5', 6),
            $this->mathService->multiply($trailingMean, '0.5', 6)
        );
    }
}
