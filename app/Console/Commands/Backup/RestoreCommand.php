<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to restore from backup
 * Usage: php artisan backup:restore {id} [--force]
 */
class RestoreCommand extends Command
{
    protected $signature = 'backup:restore
                            {id : Backup log ID to restore from}
                            {--force : Skip confirmation prompt}
                            {--skip-verify : Skip verification step}';

    protected $description = 'Restore system from a backup';

    public function handle(BackupService $backupService): int
    {
        $id = $this->argument('id');

        $log = BackupLog::find($id);

        if (! $log) {
            $this->error("Backup log with ID {$id} not found.");

            return self::FAILURE;
        }

        if (! $log->isSuccessful()) {
            $this->error('Cannot restore from a failed or incomplete backup.');

            return self::FAILURE;
        }

        $this->warn('⚠️  RESTORE OPERATION ⚠️');
        $this->newLine();
        $this->info('Backup Details:');
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $log->id],
                ['Name', $log->backup_name],
                ['Type', $log->backup_type],
                ['Created', $log->completed_at?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Size', $log->formatted_size],
                ['Verified', $log->isVerified() ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();
        $this->warn('This will OVERWRITE your current database and potentially files!');

        if (! $this->option('force')) {
            $this->warn("Please type the backup name to confirm: {$log->backup_name}");
            $confirmation = $this->ask('Confirmation');

            if ($confirmation !== $log->backup_name) {
                $this->error('Confirmation failed. Operation cancelled.');

                return self::FAILURE;
            }
        }

        $this->info('Starting restore process...');

        try {
            $success = $backupService->restoreBackup(
                $log,
                ! $this->option('skip-verify')
            );

            if ($success) {
                $this->info('✓ Restore completed successfully!');

                Log::info('Backup restore completed', [
                    'log_id' => $log->id,
                    'backup_name' => $log->backup_name,
                ]);

                return self::SUCCESS;
            }

            $this->error('Restore failed.');

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Restore failed: '.$e->getMessage());
            Log::error('Backup restore failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
