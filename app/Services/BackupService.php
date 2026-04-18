<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

/**
 * Backup Service
 * Handles manual backup triggers, verification, restore, and health checks for CEMS-MY
 */
class BackupService
{
    /**
     * Run manual backup
     */
    public function runBackup(
        string $type = BackupLog::TYPE_FULL,
        ?string $disk = null,
        ?User $user = null,
        ?string $name = null
    ): BackupLog {
        $disk ??= BackupLog::DISK_LOCAL;
        $name ??= config('backup.backup.name').'-'.now()->format('Y-m-d-H-i-s');

        $log = BackupLog::create([
            'user_id' => $user?->id,
            'backup_name' => $name,
            'backup_type' => $type,
            'disk' => $disk,
            'status' => BackupLog::STATUS_RUNNING,
            'started_at' => now(),
            'metadata' => [
                'triggered_by' => $user?->email ?? 'system',
                'ip_address' => request()?->ip(),
            ],
        ]);

        try {
            // Set backup configuration for this run
            Config::set('backup.backup.destination.disks', [$disk]);

            // Run the backup using Spatie
            $backupJob = BackupJobFactory::createFromArray(config('backup.backup'));

            if ($type === BackupLog::TYPE_DATABASE) {
                $backupJob->dontBackupFilesystem();
            } elseif ($type === BackupLog::TYPE_FILES) {
                $backupJob->dontBackupDatabases();
            }

            $backupJob->run();

            // Get the latest backup file info
            $backupDestination = BackupDestination::create($disk, config('backup.backup.name'));
            $newestBackup = $backupDestination->newestBackup();

            if ($newestBackup) {
                $log->markAsCompleted(
                    $newestBackup->path(),
                    $newestBackup->sizeInBytes(),
                    $this->calculateChecksum($newestBackup->path(), $disk)
                );
            } else {
                throw new \RuntimeException('Backup completed but no backup file found');
            }

            Log::info('Backup completed successfully', [
                'log_id' => $log->id,
                'name' => $name,
                'type' => $type,
            ]);

        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error('Backup failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $log;
    }

    /**
     * Calculate SHA-256 checksum of backup file
     */
    public function calculateChecksum(string $path, string $disk): ?string
    {
        try {
            if ($disk === BackupLog::DISK_LOCAL) {
                $fullPath = storage_path('app/'.$path);
                if (file_exists($fullPath)) {
                    return hash_file('sha256', $fullPath);
                }
            } else {
                // For S3, download and calculate checksum
                $disk = Storage::disk($disk);
                if ($disk->exists($path)) {
                    $content = $disk->get($path);

                    return hash('sha256', $content);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to calculate checksum', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackup(BackupLog $log): bool
    {
        try {
            if (! $log->file_path) {
                $log->markAsVerified(false, 'No backup file path recorded');

                return false;
            }

            // Check if file exists
            if ($log->disk === BackupLog::DISK_LOCAL) {
                $fullPath = storage_path('app/'.$log->file_path);
                if (! file_exists($fullPath)) {
                    $log->markAsVerified(false, 'Backup file not found on disk');

                    return false;
                }

                // Verify checksum
                if ($log->checksum) {
                    $currentChecksum = hash_file('sha256', $fullPath);
                    if ($currentChecksum !== $log->checksum) {
                        $log->markAsVerified(false, 'Checksum mismatch - file may be corrupted');

                        return false;
                    }
                }

                // Try to open ZIP and verify contents
                $zip = new \ZipArchive;
                if ($zip->open($fullPath) !== true) {
                    $log->markAsVerified(false, 'Unable to open ZIP archive');

                    return false;
                }

                // Check if archive is valid
                if ($zip->numFiles === 0) {
                    $zip->close();
                    $log->markAsVerified(false, 'ZIP archive is empty');

                    return false;
                }

                $zip->close();
            } else {
                // S3 verification
                $disk = Storage::disk($log->disk);
                if (! $disk->exists($log->file_path)) {
                    $log->markAsVerified(false, 'Backup file not found on S3');

                    return false;
                }

                // Verify checksum for S3
                if ($log->checksum) {
                    $content = $disk->get($log->file_path);
                    $currentChecksum = hash('sha256', $content);
                    if ($currentChecksum !== $log->checksum) {
                        $log->markAsVerified(false, 'Checksum mismatch on S3');

                        return false;
                    }
                }
            }

            $log->markAsVerified(true);

            Log::info('Backup verified successfully', [
                'log_id' => $log->id,
                'backup_name' => $log->backup_name,
            ]);

            return true;

        } catch (\Exception $e) {
            $log->markAsVerified(false, $e->getMessage());
            Log::error('Backup verification failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(BackupLog $log, bool $verifyFirst = true): bool
    {
        if ($verifyFirst && ! $log->isVerified()) {
            if (! $this->verifyBackup($log)) {
                throw new \RuntimeException('Backup verification failed - cannot restore unverified backup');
            }
        }

        try {
            Log::info('Starting backup restore', [
                'log_id' => $log->id,
                'backup_name' => $log->backup_name,
            ]);

            if ($log->disk === BackupLog::DISK_LOCAL) {
                $backupPath = storage_path('app/'.$log->file_path);
            } else {
                // Download from S3
                $tempPath = storage_path('app/backup-temp/restore-'.time().'.zip');
                $content = Storage::disk($log->disk)->get($log->file_path);
                file_put_contents($tempPath, $content);
                $backupPath = $tempPath;
            }

            $zip = new \ZipArchive;
            if ($zip->open($backupPath) !== true) {
                throw new \RuntimeException('Unable to open backup archive');
            }

            $extractPath = storage_path('app/backup-temp/restore-extract-'.time());
            $zip->extractTo($extractPath);
            $zip->close();

            // Find database dump
            $dbFiles = glob($extractPath.'/*/db-dumps/*.sql');
            if (! empty($dbFiles)) {
                $this->restoreDatabase($dbFiles[0]);
            }

            // Restore files if needed
            $sourcePath = $extractPath.'*/'.basename(base_path());
            // Application files restoration would go here
            // This is typically done manually or with additional verification

            // Cleanup
            $this->recursiveDelete($extractPath);
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            Log::info('Backup restored successfully', [
                'log_id' => $log->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Backup restore failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Restore database from SQL dump
     */
    protected function restoreDatabase(string $sqlFile): void
    {
        $dbConfig = config('database.connections.mysql');

        $command = sprintf(
            'mysql -h %s -P %s -u %s %s %s < %s',
            escapeshellarg($dbConfig['host'] ?? 'localhost'),
            escapeshellarg($dbConfig['port'] ?? '3306'),
            escapeshellarg($dbConfig['username'] ?? 'root'),
            $dbConfig['password'] ? '-p'.escapeshellarg($dbConfig['password']) : '',
            escapeshellarg($dbConfig['database']),
            escapeshellarg($sqlFile)
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            throw new \RuntimeException('Database restore failed: '.$result->errorOutput());
        }
    }

    /**
     * Run health checks on backup system
     */
    public function runHealthChecks(): array
    {
        $results = [];

        // Check 1: Recent backup exists
        $latestBackup = BackupLog::latestSuccessful();
        if ($latestBackup) {
            $ageInDays = $latestBackup->completed_at->diffInDays(now());
            $results['recent_backup'] = [
                'passed' => $ageInDays <= 1,
                'message' => $ageInDays <= 1
                    ? 'Recent backup found ('.$ageInDays.' days old)'
                    : 'No recent backup (last backup '.$ageInDays.' days ago)',
                'last_backup' => $latestBackup->backup_name,
                'last_backup_date' => $latestBackup->completed_at->toDateTimeString(),
            ];
        } else {
            $results['recent_backup'] = [
                'passed' => false,
                'message' => 'No successful backups found',
            ];
        }

        // Check 2: Storage space
        $localDisk = Storage::disk('local');
        $freeSpace = disk_free_space(storage_path());
        $totalSpace = disk_total_space(storage_path());
        $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        $results['storage_space'] = [
            'passed' => $usedPercentage < 90,
            'message' => sprintf('Storage usage: %.1f%%', $usedPercentage),
            'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
        ];

        // Check 3: Backup directory writable
        $backupPath = storage_path('app/'.config('backup.backup.name'));
        $results['backup_writable'] = [
            'passed' => is_writable(dirname($backupPath)),
            'message' => is_writable(dirname($backupPath))
                ? 'Backup directory is writable'
                : 'Backup directory is not writable',
        ];

        // Check 4: Failed backups in last 24 hours
        $recentFailures = BackupLog::where('status', BackupLog::STATUS_FAILED)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $results['recent_failures'] = [
            'passed' => $recentFailures === 0,
            'message' => $recentFailures === 0
                ? 'No failed backups in last 24 hours'
                : $recentFailures.' failed backups in last 24 hours',
            'failure_count' => $recentFailures,
        ];

        // Check 5: Verified backups
        $unverifiedBackups = BackupLog::completed()
            ->whereNull('verification_status')
            ->where('completed_at', '<=', now()->subHours(1))
            ->count();

        $results['verification_status'] = [
            'passed' => $unverifiedBackups === 0,
            'message' => $unverifiedBackups === 0
                ? 'All recent backups verified'
                : $unverifiedBackups.' unverified backups pending',
            'pending_count' => $unverifiedBackups,
        ];

        // Overall status
        $results['overall'] = [
            'passed' => ! in_array(false, array_column($results, 'passed'), true),
            'checked_at' => now()->toDateTimeString(),
        ];

        return $results;
    }

    /**
     * Clean old backups based on retention policy
     */
    public function cleanOldBackups(): array
    {
        $deleted = [];

        // Use Spatie's cleanup command logic
        $backupDestinations = BackupDestination::forCurrentDisk(
            config('backup.backup.destination.disks')
        );

        foreach ($backupDestinations as $backupDestination) {
            $backups = $backupDestination->backups();

            // Apply retention policy
            $strategy = app(config('backup.cleanup.strategy'));
            $backupsToDelete = $strategy->deleteBackups($backups);

            foreach ($backupsToDelete as $backup) {
                $backup->delete();
                $deleted[] = [
                    'path' => $backup->path(),
                    'date' => $backup->date()->toDateTimeString(),
                ];
            }
        }

        Log::info('Backup cleanup completed', [
            'deleted_count' => count($deleted),
        ]);

        return $deleted;
    }

    /**
     * Archive backup to long-term storage (S3 Glacier)
     */
    public function archiveBackup(BackupLog $log): bool
    {
        try {
            if ($log->disk === BackupLog::DISK_S3) {
                Log::info('Backup already on S3, skipping archive', [
                    'log_id' => $log->id,
                ]);

                return true;
            }

            $sourcePath = storage_path('app/'.$log->file_path);
            if (! file_exists($sourcePath)) {
                throw new \RuntimeException('Source backup file not found');
            }

            // Upload to S3 with Glacier storage class
            $s3Disk = Storage::disk('s3');
            $archivePath = 'archives/'.basename($log->file_path);

            $s3Disk->put(
                $archivePath,
                file_get_contents($sourcePath),
                ['StorageClass' => 'GLACIER']
            );

            // Update log
            $log->update([
                'metadata' => array_merge($log->metadata ?? [], [
                    'archived_to_s3' => true,
                    'archive_path' => $archivePath,
                    'archived_at' => now()->toDateTimeString(),
                ]),
            ]);

            Log::info('Backup archived to S3 Glacier', [
                'log_id' => $log->id,
                'archive_path' => $archivePath,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Backup archive failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List all backups
     */
    public function listBackups(?string $disk = null): array
    {
        $backups = [];

        $disks = $disk ? [$disk] : config('backup.backup.destination.disks');

        foreach ($disks as $diskName) {
            $backupDestination = BackupDestination::create($diskName, config('backup.backup.name'));

            foreach ($backupDestination->backups() as $backup) {
                $backups[] = [
                    'path' => $backup->path(),
                    'disk' => $diskName,
                    'size' => $backup->sizeInBytes(),
                    'date' => $backup->date()->toDateTimeString(),
                ];
            }
        }

        // Sort by date descending
        usort($backups, fn ($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return $backups;
    }

    /**
     * Recursively delete directory
     */
    protected function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($dir);
        }
    }
}
