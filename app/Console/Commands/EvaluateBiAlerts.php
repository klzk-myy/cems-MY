<?php

namespace App\Console\Commands;

use App\Services\Bi\BiAlertEvaluator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EvaluateBiAlerts extends Command
{
    protected $signature = 'bi:evaluate-alerts {--date= : Rollup day to evaluate (default yesterday)}';

    protected $description = 'Evaluate BI alert rules against the analytics rollups and raise system alerts on breaches';

    public function handle(BiAlertEvaluator $evaluator): int
    {
        $day = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : Carbon::yesterday();

        $counts = $evaluator->evaluateDay($day);

        $this->info(
            "Evaluated {$counts['evaluated']} rule(s) for {$day->toDateString()}: ".
            "{$counts['breached']} breached, {$counts['alerted']} alerted."
        );

        return self::SUCCESS;
    }
}
