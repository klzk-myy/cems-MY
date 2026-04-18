<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to run manual backup
 * Usage: php artisan backup:run [--type=database|files|full] [--disk=local|s3]
 */
class RunCommand extends Command
{
    protected $signature = 'backup:run
                            {--type=full : Type of backup (database, files, full)}
                            {--disk=local : Storage disk (local, s3)}
                            {--name= : Custom backup name}';

    protected $description = 'Run a manual backup of the system';

    public function handle(BackupService $backupService): int
    {
        $type = $this->option('type');
        $disk = $this->option('disk');
        $name = $this->option('name');

        // Validate type
        if (! in_array($type, [BackupLog::TYPE_DATABASE, BackupLog::TYPE_FILES, BackupLog::TYPE_FULL], true)) {
            $this->error("Invalid backup type: {$type}. Allowed: database, files, full");

            return self::FAILURE;
        }

        // Validate disk
        if (! in_array($disk, [BackupLog::DISK_LOCAL, BackupLog::DISK_S3], true)) {
            $this->error("Invalid disk: {$disk}. Allowed: local, s3");

            return self::FAILURE;
        }

        $this->info("Starting {$type} backup to {$disk}...");

        try {
            $log = $backupService->runBackup(
                type: $type,
                disk: $disk,
                name: $name
            );

            if ($log->isSuccessful()) {
                $this->info('Backup completed successfully!');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['ID', $log->id],
                        ['Name', $log->backup_name],
                        ['Type', $log->backup_type],
                        ['Disk', $log->disk],
                        ['Size', $log->formatted_size],
                        ['Path', $log->file_path ?? 'N/A'],
                        ['Duration', $log->duration ? $log->duration.'s' : 'N/A'],
                    ]
                );

                Log::info('Manual backup completed', [
                    'log_id' => $log->id,
                    'type' => $type,
                    'disk' => $disk,
                ]);

                return self::SUCCESS;
            }

            $this->error('Backup failed: '.$log->error_message);

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Backup failed: '.$e->getMessage());
            Log::error('Manual backup command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
