<?php

namespace App\Console\Commands;

use App\Services\LogRotationService;
use Illuminate\Console\Command;

class RotateAuditLogs extends Command
{
    protected $signature = 'audit:rotate
                            {--days=1825 : Retention period in days (default: 5 years for BNM compliance)}
                            {--cleanup : Also cleanup old archives (> 10 years)}
                            {--dry-run : Show what would be archived without executing}';

    protected $description = 'Archive old audit logs and optionally cleanup old archives';

    public function handle(LogRotationService $service): int
    {
        $retentionDays = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info("Archiving logs older than {$retentionDays} days...");

        if ($isDryRun) {
            $stats = $service->getArchiveStats();
            $cutoffDate = now()->subDays($retentionDays)->toDateString();

            $this->info("Current log count: {$stats['total_logs']}");
            $this->info("Archive cutoff date: {$cutoffDate}");
            $this->info('Archives will be saved to: storage/app/archives/');

            return self::SUCCESS;
        }

        $result = $service->archiveOldLogs($retentionDays);

        if ($result['archived'] > 0) {
            $this->info("✓ Archived {$result['archived']} logs to {$result['file']}");
        } else {
            $this->info('✓ No logs to archive');
        }

        if ($this->option('cleanup')) {
            $this->info('Cleaning up old archives (> 10 years)...');
            $deleted = $service->cleanupOldArchives(3650); // 10 years
            $this->info("✓ Deleted {$deleted} old archive files");
        }

        $this->info('Audit log rotation completed!');

        return self::SUCCESS;
    }
}
