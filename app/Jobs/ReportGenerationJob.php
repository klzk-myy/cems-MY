<?php

namespace App\Jobs;

use App\Services\ReportingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $reportType,
        public string $date
    ) {}

    public function handle(ReportingService $service): void
    {
        $start = microtime(true);

        // Generate the report
        $result = $service->generateReport($this->reportType, $this->date);

        $duration = (microtime(true) - $start) * 1000;

        Log::info('Report generation job completed', [
            'report_type' => $this->reportType,
            'date' => $this->date,
            'duration_ms' => round($duration, 2),
        ]);

        if ($duration > 10000) {
            Log::warning('Slow report generation job', [
                'report_type' => $this->reportType,
                'date' => $this->date,
                'duration_ms' => round($duration, 2),
            ]);
        }
    }

    public function tags(): array
    {
        return ['report', $this->reportType];
    }
}
