<?php

namespace App\Services\Bi;

use App\Enums\BiAlertComparison;
use App\Enums\SystemAlertLevel;
use App\Models\Bi\BiAlertRule;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates active BiAlertRule rows against the analytics rollups for a
 * single day (yesterday by default — the last fully-built rollup day).
 * A breaching rule raises a SystemAlert at the rule's level; warning and
 * critical alerts also email the rule's recipients (or the system
 * defaults when empty). A 24h cooldown on last_triggered_at prevents a
 * still-breaching rule from spamming recipients every run.
 */
class BiAlertEvaluator
{
    /** Minimum hours between repeat alerts for a continuously-breaching rule. */
    public const COOLDOWN_HOURS = 24;

    public function __construct(
        protected KpiService $kpiService,
        protected MathService $mathService,
        protected SystemAlertService $alertService,
    ) {}

    /**
     * Evaluate every active rule for one rollup day.
     *
     * @return array{evaluated: int, breached: int, alerted: int}
     */
    public function evaluateDay(?Carbon $day = null): array
    {
        $day ??= Carbon::yesterday();
        $counts = ['evaluated' => 0, 'breached' => 0, 'alerted' => 0];

        BiAlertRule::active()->with('branch')->get()->each(function (BiAlertRule $rule) use ($day, &$counts) {
            $counts['evaluated']++;

            $metrics = $this->kpiService->periodMetrics($day, $day, $rule->branch_id);
            $value = $metrics[$rule->metric] ?? null;

            if ($value === null) {
                Log::warning('BI alert rule references unknown metric', [
                    'rule_id' => $rule->id,
                    'metric' => $rule->metric,
                ]);

                return;
            }

            $rule->last_evaluated_at = now();

            if (! $this->breaches((string) $value, $rule->comparison, (string) $rule->threshold)) {
                $rule->save();

                return;
            }

            $counts['breached']++;

            if ($this->inCooldown($rule)) {
                $rule->save();

                return;
            }

            $this->raiseAlert($rule, (string) $value, $day);
            $rule->last_triggered_at = now();
            $rule->save();
            $counts['alerted']++;
        });

        return $counts;
    }

    protected function breaches(string $value, BiAlertComparison $comparison, string $threshold): bool
    {
        if (! is_numeric($value) || ! is_numeric($threshold)) {
            return false;
        }

        return $comparison->breaches(bccomp($value, $threshold, 4));
    }

    protected function inCooldown(BiAlertRule $rule): bool
    {
        return $rule->last_triggered_at !== null
            && $rule->last_triggered_at->gt(now()->subHours(self::COOLDOWN_HOURS));
    }

    protected function raiseAlert(BiAlertRule $rule, string $value, Carbon $day): void
    {
        $metricLabel = BiAlertRule::availableMetrics()[$rule->metric] ?? $rule->metric;
        // branch_id cascades on delete, so a non-null FK always resolves.
        $branchName = $rule->branch_id === null ? 'all branches' : $rule->branch->name;
        $level = $rule->level === SystemAlertLevel::Critical
            ? SystemAlertLevel::Critical
            : SystemAlertLevel::Warning;

        $this->alertService->send(
            "BI alert: {$metricLabel} {$value} {$rule->comparison->value} {$rule->threshold} for {$branchName} on {$day->toDateString()}",
            $level->value,
            [
                'source' => 'bi_alert_rule',
                'recipients' => $rule->notification_recipients ?? [],
                'metadata' => [
                    'rule_id' => $rule->id,
                    'metric' => $rule->metric,
                    'value' => $value,
                    'comparison' => $rule->comparison->value,
                    'threshold' => $rule->threshold,
                    'branch_id' => $rule->branch_id,
                    'date' => $day->toDateString(),
                ],
            ]
        );
    }
}
