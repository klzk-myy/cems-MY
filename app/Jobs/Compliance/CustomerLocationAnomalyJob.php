<?php

namespace App\Jobs\Compliance;

use App\Services\Compliance\MonitoringEngine;
use App\Services\Compliance\Monitors\CustomerLocationAnomalyMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CustomerLocationAnomalyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function handle(MonitoringEngine $engine): void
    {
        Log::info('CustomerLocationAnomalyJob started');
        $engine->runMonitor(CustomerLocationAnomalyMonitor::class);
        Log::info('CustomerLocationAnomalyJob completed');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(static::class.' permanently failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
