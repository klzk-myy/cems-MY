<?php

namespace App\Console\Commands;

use App\Services\Bi\VolumeAnomalyDetector;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DetectBiVolumeAnomalies extends Command
{
    protected $signature = 'bi:detect-anomalies
                            {--date= : Rollup day to evaluate (default yesterday)}
                            {--threshold=3 : Z-score that flags a day}';

    protected $description = 'Flag daily trade volume that deviates from a branch\'s rolling baseline';

    public function handle(VolumeAnomalyDetector $detector): int
    {
        $day = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : Carbon::yesterday();

        $counts = $detector->detect($day, (float) $this->option('threshold'));

        $this->info(
            "Evaluated {$counts['evaluated']} branch(es) for {$day->toDateString()}: ".
            "{$counts['flagged']} flagged."
        );

        return self::SUCCESS;
    }
}
