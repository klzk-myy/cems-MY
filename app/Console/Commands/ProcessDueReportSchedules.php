<?php

namespace App\Console\Commands;

use App\Services\Reporting\ReportSchedulingService;
use Illuminate\Console\Command;

class ProcessDueReportSchedules extends Command
{
    protected $signature = 'reports:process-schedules';

    protected $description = 'Process due report schedules and generate their reports';

    public function handle(ReportSchedulingService $reportSchedulingService): int
    {
        $summary = $reportSchedulingService->processDueSchedules();

        $this->info("Due report schedules: {$summary['due']}");
        $this->info("Processed successfully: {$summary['processed']}");
        $this->info("Failed: {$summary['failed']}");

        return Command::SUCCESS;
    }
}
