<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Models\SystemAlert;
use App\Services\System\SystemAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckRateStalenessCommand extends Command
{
    protected $signature = 'rates:staleness-check';

    protected $description = 'Raise a system alert when exchange rates have not been refreshed within the configured window';

    public function handle(SystemAlertService $alertService): int
    {
        $maxAgeHours = (int) config('cems.rate_staleness_hours', 8);

        $lastUpdate = ExchangeRate::query()->max('updated_at');

        if ($lastUpdate === null) {
            $this->info('No exchange rates stored yet; skipping staleness check.');

            return self::SUCCESS;
        }

        $lastUpdate = Carbon::parse($lastUpdate);
        $ageHours = $lastUpdate->diffInHours(now(), false);

        if ($ageHours < $maxAgeHours) {
            $this->info("Exchange rates are fresh (last updated {$lastUpdate->toDateTimeString()}).");

            return self::SUCCESS;
        }

        // Avoid alert storms: only one open staleness alert at a time.
        $alreadyAlerting = SystemAlert::query()
            ->where('source', 'rate_staleness')
            ->unacknowledged()
            ->exists();

        if ($alreadyAlerting) {
            $this->info('Staleness alert already active; not raising a duplicate.');

            return self::SUCCESS;
        }

        $alertService->warning(
            "Exchange rates are stale: last refresh was {$lastUpdate->toDateTimeString()} ".
            "({$ageHours} hours ago), exceeding the {$maxAgeHours}-hour threshold.",
            [
                'source' => 'rate_staleness',
                'metadata' => [
                    'last_updated_at' => $lastUpdate->toIso8601String(),
                    'age_hours' => (int) $ageHours,
                    'threshold_hours' => $maxAgeHours,
                ],
            ]
        );

        $this->warn("Exchange rates are stale ({$ageHours}h old); warning alert raised.");

        return self::SUCCESS;
    }
}
