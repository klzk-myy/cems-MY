<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to clean old backups based on retention policy
 * Usage: php artisan backup:clean
 */
class CleanCommand extends Command
{
    protected $signature = 'backup:clean
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clean old backups based on retention policy';

    public function handle(BackupService $backupService): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will delete old backups based on the retention policy. Continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Cleaning old backups...');

        try {
            $deleted = $backupService->cleanOldBackups();

            if (empty($deleted)) {
                $this->info('No backups needed to be cleaned.');

                return self::SUCCESS;
            }

            $this->info('Deleted '.count($deleted).' old backup(s):');

            $rows = [];
            foreach ($deleted as $backup) {
                $rows[] = [
                    $backup['path'],
                    $backup['date'],
                ];
            }

            $this->table(['Path', 'Date'], $rows);

            Log::info('Backup cleanup completed', [
                'deleted_count' => count($deleted),
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: '.$e->getMessage());
            Log::error('Backup cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
