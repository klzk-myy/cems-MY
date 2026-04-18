<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use Illuminate\Console\Command;

class CleanupOldReports extends Command
{
    protected $signature = 'reports:cleanup {--days=90 : Delete reports older than N days}';

    protected $description = 'Clean up old generated report files';

    public function handle(ExportService $exportService): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up reports older than {$days} days...");

        $deleted = $exportService->cleanupOldReports($days);

        $this->info("Deleted {$deleted} old report files.");

        return 0;
    }
}
