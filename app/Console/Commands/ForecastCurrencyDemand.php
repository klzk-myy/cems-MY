<?php

namespace App\Console\Commands;

use App\Services\Bi\DemandForecaster;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ForecastCurrencyDemand extends Command
{
    protected $signature = 'bi:forecast {--horizon=7 : Forecast window in days} {--start= : First forecast date (default tomorrow)}';

    protected $description = 'Generate currency demand forecasts from the analytics rollups (seasonal blend)';

    public function handle(DemandForecaster $forecaster): int
    {
        $start = $this->option('start')
            ? Carbon::parse((string) $this->option('start'))
            : Carbon::tomorrow();
        $horizon = max(1, (int) $this->option('horizon'));

        $written = $forecaster->generate($start, $horizon);

        $this->info("Wrote {$written} forecast row(s) for {$start->toDateString()} + {$horizon} day(s).");

        return self::SUCCESS;
    }
}
