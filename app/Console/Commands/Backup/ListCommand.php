<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Command to list all backups
 * Usage: php artisan backup:list [--disk=local|s3]
 */
class ListCommand extends Command
{
    protected $signature = 'backup:list
                            {--disk= : Filter by disk (local, s3)}
                            {--type= : Filter by type (database, files, full, manual)}
                            {--limit=20 : Maximum number of backups to show}';

    protected $description = 'List all backups';

    public function handle(BackupService $backupService): int
    {
        $disk = $this->option('disk');
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        // Get backups from both database and filesystem
        $this->info('Retrieving backup list...');

        $query = BackupLog::query()
            ->orderByDesc('started_at')
            ->limit($limit);

        if ($disk) {
            $query->where('disk', $disk);
        }

        if ($type) {
            $query->where('backup_type', $type);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->warn('No backups found.');

            return self::SUCCESS;
        }

        $this->info("Found {$logs->count()} backup(s):");

        $rows = [];
        foreach ($logs as $log) {
            $status = match ($log->status) {
                BackupLog::STATUS_COMPLETED => '<fg=green>✓ Completed</>',
                BackupLog::STATUS_VERIFIED => '<fg=green>✓ Verified</>',
                BackupLog::STATUS_FAILED => '<fg=red>✗ Failed</>',
                BackupLog::STATUS_RUNNING => '<fg=yellow>⋯ Running</>',
                BackupLog::STATUS_VERIFICATION_FAILED => '<fg=red>✗ Verification Failed</>',
                default => '<fg=gray>? '.$log->status.'</>',
            };

            $rows[] = [
                $log->id,
                Str::limit($log->backup_name, 30),
                $log->backup_type,
                $log->disk,
                $log->formatted_size,
                $status,
                $log->started_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['ID', 'Name', 'Type', 'Disk', 'Size', 'Status', 'Started'],
            $rows
        );

        // Summary
        $stats = BackupLog::getStatistics(30);
        $this->newLine();
        $this->info('Last 30 Days Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Backups', $stats['total_count']],
                ['Successful', $stats['successful_count']],
                ['Failed', $stats['failed_count']],
                ['Verified', $stats['verified_count']],
                ['Total Size', $this->formatBytes($stats['total_size'])],
                ['Avg Duration', $stats['average_duration'] ? round($stats['average_duration'], 1).'s' : 'N/A'],
            ]
        );

        return self::SUCCESS;
    }

    private function formatBytes($bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return 'N/A';
        }

        $bytes = (int) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
