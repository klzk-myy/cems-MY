<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Enums\SystemAlertLevel;
use App\Models\Bi\AnalyticsDailyBranch;
use App\Models\Branch;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Flags daily trade volume that deviates from a branch's rolling baseline.
 *
 * Each branch's most recent fully-built day is compared against the mean
 * and variance of the preceding 90 days. The comparison is done on squared
 * deviations, so no square root is needed: a day flags when
 * `(observed - mean)² / variance` reaches the squared z-threshold. Branches
 * without enough history to form a baseline are skipped rather than guessed
 * at, and a flat baseline (zero variance) can never flag.
 */
class VolumeAnomalyDetector
{
    /** Baseline window in days, ending the day before the evaluated day. */
    public const BASELINE_DAYS = 90;

    /** Minimum baseline observations before a deviation is meaningful. */
    public const MINIMUM_BASELINE = 14;

    public function __construct(
        protected MathService $mathService,
        protected SystemAlertService $alertService,
    ) {}

    /**
     * Evaluate every active branch for one day.
     *
     * @return array{evaluated: int, flagged: int}
     */
    public function detect(?Carbon $day = null, float $threshold = 3.0): array
    {
        $day ??= Carbon::yesterday();
        $counts = ['evaluated' => 0, 'flagged' => 0];

        $observed = $this->dailyVolumes($day, $day);
        $baseline = $this->dailyVolumes(
            $day->copy()->subDays(self::BASELINE_DAYS),
            $day->copy()->subDay()
        );

        $limit = $this->mathService->multiply(
            (string) $threshold,
            (string) $threshold,
            4
        );

        foreach (Branch::query()->active()->get() as $branch) {
            $series = $baseline
                ->get($branch->id, collect())
                ->map(fn ($row) => (string) $row->volume)
                ->values();

            $observedRow = $observed->get($branch->id)?->first();
            $deviation = $this->deviation($series, $observedRow === null ? '0' : (string) $observedRow->volume);

            if ($deviation === null) {
                continue;
            }

            $counts['evaluated']++;

            if ($this->mathService->compare($deviation['z_squared'], $limit) < 0) {
                continue;
            }

            $counts['flagged']++;

            $this->raiseAlert($branch, $day, $deviation['observed'], $deviation['direction']);
        }

        return $counts;
    }

    /**
     * Daily total volume per branch across a date range, keyed by branch.
     *
     * @return Collection<int, Collection<int, \stdClass>>
     */
    protected function dailyVolumes(Carbon $from, Carbon $to): Collection
    {
        return AnalyticsDailyBranch::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('branch_id')
            ->groupBy('branch_id', 'date')
            ->selectRaw('branch_id')
            ->selectRaw('date')
            ->selectRaw('SUM(buy_volume_myr + sell_volume_myr) as volume')
            ->toBase()
            ->get()
            ->groupBy('branch_id');
    }

    /**
     * Mean deviation of the observed value from the baseline series,
     * expressed as a squared z-score so the threshold needs no square root.
     *
     * @param  Collection<int, string>  $series
     * @return array{observed: string, direction: string, z_squared: string}|null
     */
    protected function deviation(Collection $series, string $observed): ?array
    {
        $count = $series->count();

        if ($count < self::MINIMUM_BASELINE) {
            return null;
        }

        $sum = $series->reduce(
            fn ($carry, string $value) => $this->mathService->add($carry, $value),
            '0'
        );
        $mean = $this->mathService->divide($sum, (string) $count, 8);

        $squaredSum = $series->reduce(function (string $carry, string $value) use ($mean): string {
            $difference = $this->mathService->subtract($value, $mean);

            return $this->mathService->add(
                $carry,
                $this->mathService->multiply($difference, $difference)
            );
        }, '0');
        $variance = $this->mathService->divide($squaredSum, (string) $count, 8);

        if ($this->mathService->compare($variance, '0') <= 0) {
            return null;
        }

        $difference = $this->mathService->subtract($observed, $mean);

        return [
            'observed' => $observed,
            'direction' => $this->mathService->compare($difference, '0') >= 0 ? 'surge' : 'dip',
            'z_squared' => $this->mathService->divide(
                $this->mathService->multiply($difference, $difference),
                $variance,
                8
            ),
        ];
    }

    /**
     * Raise a deduplicatable ops alert. Volume anomalies are not AML
     * events, so the source is distinct from the compliance monitors.
     */
    protected function raiseAlert(Branch $branch, Carbon $day, string $observed, string $direction): void
    {
        $this->alertService->send(
            sprintf(
                'BI volume %s: %s traded MYR %s on %s, outside its %d-day baseline',
                $direction,
                $branch->name,
                number_format((float) $observed, 2),
                $day->toDateString(),
                self::BASELINE_DAYS
            ),
            SystemAlertLevel::Warning->value,
            [
                'source' => 'bi_volume_anomaly',
                'metadata' => [
                    'branch_id' => $branch->id,
                    'date' => $day->toDateString(),
                    'direction' => $direction,
                    'volume_myr' => $observed,
                    'baseline_days' => self::BASELINE_DAYS,
                ],
            ]
        );
    }
}
