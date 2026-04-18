<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use App\Services\ReportingService;
use Illuminate\Console\Command;

class GenerateDailyMSB2 extends Command
{
    protected $signature = 'report:msb2 {--date= : Specific date (Y-m-d)}';

    protected $description = 'Generate daily MSB(2) report';

    public function handle(ReportingService $reportingService, ExportService $exportService): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Generating MSB(2) report for {$date}...");

        try {
            $report = $reportingService->generateMSB2Data($date);

            $filename = "MSB2_{$date}.csv";
            $path = $exportService->toCSV($report['data'], $filename);

            $this->info("MSB(2) report generated: {$path}");
            $this->info('Total currencies: '.count($report['data']));

            return 0;

        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
