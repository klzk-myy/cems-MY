<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasReportFormatting;
use App\Enums\ReportType;
use App\Services\Reporting\ReportingService;
use Illuminate\Console\Command;

class GeneratePositionLimitReport extends Command
{
    use HasReportFormatting;

    protected $signature = 'report:position-limit';

    protected $description = 'Generate daily position limit utilization report';

    public function handle(ReportingService $reportingService): int
    {
        $this->info('Generating Position Limit Report...');

        try {
            $filepath = $reportingService->generatePositionLimitCsv();

            $this->createReportRecord(ReportType::Plr, now()->startOfDay(), now()->endOfDay());

            $this->info("Position Limit Report generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
