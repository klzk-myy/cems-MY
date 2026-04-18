<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to verify backup integrity
 * Usage: php artisan backup:verify {id}
 */
class VerifyCommand extends Command
{
    protected $signature = 'backup:verify
                            {id? : Backup log ID to verify (optional, verifies latest if not specified)}
                            {--all : Verify all unverified backups}';

    protected $description = 'Verify backup integrity';

    public function handle(BackupService $backupService): int
    {
        if ($this->option('all')) {
            return $this->verifyAll($backupService);
        }

        $id = $this->argument('id');

        if ($id) {
            $log = BackupLog::find($id);
        } else {
            $log = BackupLog::latestSuccessful();
        }

        if (! $log) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        $this->info("Verifying backup: {$log->backup_name}");
        $this->info("Path: {$log->file_path}");
        $this->info("Type: {$log->backup_type}");

        try {
            $success = $backupService->verifyBackup($log);

            if ($success) {
                $this->info('✓ Backup verified successfully!');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['ID', $log->id],
                        ['Name', $log->backup_name],
                        ['Verified At', $log->verified_at?->format('Y-m-d H:i:s')],
                        ['Checksum Match', 'Yes'],
                    ]
                );

                return self::SUCCESS;
            }

            $this->error('✗ Backup verification failed!');
            $this->error('Error: '.$log->verification_error);

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Verification error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function verifyAll(BackupService $backupService): int
    {
        $unverified = BackupLog::completed()
            ->whereNull('verification_status')
            ->get();

        if ($unverified->isEmpty()) {
            $this->info('No unverified backups found.');

            return self::SUCCESS;
        }

        $this->info("Found {$unverified->count()} unverified backup(s).");
        $this->newLine();

        $verified = 0;
        $failed = 0;

        foreach ($unverified as $log) {
            $this->info("Verifying: {$log->backup_name}");

            try {
                if ($backupService->verifyBackup($log)) {
                    $this->info('  ✓ Verified');
                    $verified++;
                } else {
                    $this->error('  ✗ Failed: '.$log->verification_error);
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error('  ✗ Error: '.$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info('Verification complete:');
        $this->info("  Verified: {$verified}");
        $this->info("  Failed: {$failed}");

        Log::info('Batch backup verification completed', [
            'verified' => $verified,
            'failed' => $failed,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
