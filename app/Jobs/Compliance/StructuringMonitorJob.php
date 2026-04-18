<?php

namespace App\Jobs\Compliance;

use App\Services\Compliance\MonitoringEngine;
use App\Services\Compliance\Monitors\StructuringMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StructuringMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function handle(MonitoringEngine $engine): void
    {
        Log::info('StructuringMonitorJob started');
        $engine->runMonitor(StructuringMonitor::class);
        Log::info('StructuringMonitorJob completed');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(static::class.' permanently failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
