<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Command to monitor backup health
 * Usage: php artisan backup:monitor
 */
class MonitorCommand extends Command
{
    protected $signature = 'backup:monitor
                            {--notify : Send notification if issues found}
                            {--fix : Attempt to fix detected issues}';

    protected $description = 'Monitor backup system health';

    public function handle(BackupService $backupService): int
    {
        $this->info('Running backup health checks...');
        $this->newLine();

        $results = $backupService->runHealthChecks();

        // Display results
        foreach ($results as $check => $result) {
            if ($check === 'overall') {
                continue;
            }

            $status = $result['passed'] ? '<fg=green>✓ PASS</>' : '<fg=red>✗ FAIL</>';
            $this->info("[{$status}] {$check}");
            $this->info("    {$result['message']}");

            // Show additional details
            foreach ($result as $key => $value) {
                if (! in_array($key, ['passed', 'message'], true)) {
                    $this->info("    {$key}: {$value}");
                }
            }
            $this->newLine();
        }

        // Overall status
        $overall = $results['overall'];
        $this->newLine();

        if ($overall['passed']) {
            $this->info('✓ All health checks passed!');

            return self::SUCCESS;
        }

        $this->error('✗ Some health checks failed!');
        $this->info("Checked at: {$overall['checked_at']}");

        // Send notification if requested
        if ($this->option('notify')) {
            $this->sendNotification($results);
        }

        // Attempt fixes if requested
        if ($this->option('fix')) {
            $this->attemptFixes($results, $backupService);
        }

        return self::FAILURE;
    }

    private function sendNotification(array $results): void
    {
        $failedChecks = [];
        foreach ($results as $check => $result) {
            if ($check !== 'overall' && ! $result['passed']) {
                $failedChecks[$check] = $result['message'];
            }
        }

        $message = "Backup Health Check Alert\n";
        $message .= 'Time: '.now()->toDateTimeString()."\n\n";
        $message .= "Failed Checks:\n";
        foreach ($failedChecks as $check => $message_text) {
            $message .= "- {$check}: {$message_text}\n";
        }

        // Log the notification
        Log::warning('Backup health check failed', [
            'failed_checks' => array_keys($failedChecks),
            'message' => $message,
        ]);

        $this->info('Notification logged (email/slack integration needed)');
    }

    private function attemptFixes(array $results, BackupService $backupService): void
    {
        $this->info('Attempting automatic fixes...');

        // Fix: Run backup if no recent backup
        if (isset($results['recent_backup']) && ! $results['recent_backup']['passed']) {
            $this->warn('Triggering emergency backup...');
            try {
                $log = $backupService->runBackup(
                    type: BackupLog::TYPE_DATABASE,
                    name: 'emergency-backup-'.now()->format('Y-m-d-H-i-s')
                );

                if ($log->isSuccessful()) {
                    $this->info('✓ Emergency backup completed');
                }
            } catch (\Exception $e) {
                $this->error('Emergency backup failed: '.$e->getMessage());
            }
        }

        // Fix: Verify unverified backups
        if (isset($results['verification_status']) && ! $results['verification_status']['passed']) {
            $this->warn('Running verification on pending backups...');

            $unverified = BackupLog::completed()
                ->whereNull('verification_status')
                ->get();

            foreach ($unverified as $log) {
                try {
                    $backupService->verifyBackup($log);
                    $this->info("  ✓ Verified: {$log->backup_name}");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed: {$log->backup_name}");
                }
            }
        }

        $this->info('Automatic fixes completed');
    }
}
