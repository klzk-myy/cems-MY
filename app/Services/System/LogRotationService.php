<?php

namespace App\Services\System;

use App\Exceptions\Domain\LogArchiveException;
use App\Models\SystemLog;
use App\Services\AuditService;
use Carbon\Carbon;

class LogRotationService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Default retention period in days
     * BNM AML/CFT requires 5-year retention for compliance records
     */
    protected int $defaultRetentionDays = 1825; // 5 years

    /**
     * Archive logs older than retention period
     */
    public function archiveOldLogs(?int $retentionDays = null): array
    {
        $retentionDays = $retentionDays ?? $this->defaultRetentionDays;
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $archiveFilename = 'system_logs_archive_'.now()->format('Y_m_d_His').'.json';
        $archivePath = storage_path('app/archives/'.$archiveFilename);
        $archiveDir = dirname($archivePath);

        if (! file_exists($archiveDir) && ! mkdir($archiveDir, 0755, true) && ! is_dir($archiveDir)) {
            throw new LogArchiveException("Failed to create archive directory: {$archiveDir}", $archiveDir);
        }

        $handle = fopen($archivePath, 'w');
        if (! $handle) {
            throw new LogArchiveException("Failed to open archive file: {$archivePath}", $archivePath);
        }

        $writeRow = function ($log, bool $first) {
            $json = json_encode([
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'severity' => $log->severity,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'session_id' => $log->session_id,
                'created_at' => $log->created_at->toDateTimeString(),
            ]);

            return ($first ? '' : ',').$json;
        };

        // Pass 1: stream the archive file (lazyById bounds memory; no ids are
        // collected). Nothing is deleted until the file is fully written and
        // verified, so a crash mid-archive never loses logs.
        fwrite($handle, '[');
        $first = true;
        SystemLog::where('created_at', '<', $cutoffDate)
            ->orderBy('id')
            ->lazyById(500)
            ->each(function ($log) use ($handle, $writeRow, &$first) {
                fwrite($handle, $writeRow($log, $first));
                $first = false;
            });

        fwrite($handle, ']');
        if (fclose($handle) === false) {
            throw new LogArchiveException("Failed to close archive file: {$archivePath}", $archivePath);
        }

        if (! is_file($archivePath) || filesize($archivePath) === 0) {
            throw new LogArchiveException("Archive file was not written: {$archivePath}", $archivePath);
        }

        // Pass 2: delete the archived rows in bounded chunks. A second lazyById
        // pass keeps memory bounded without holding the full id list.
        $archivedCount = 0;
        $batch = [];
        SystemLog::where('created_at', '<', $cutoffDate)
            ->orderBy('id')
            ->lazyById(500)
            ->each(function ($log) use (&$batch, &$archivedCount) {
                $batch[] = $log->id;

                if (count($batch) >= 500) {
                    $archivedCount += SystemLog::whereIn('id', $batch)->delete();
                    $batch = [];
                }
            });

        if ($batch !== []) {
            $archivedCount += SystemLog::whereIn('id', $batch)->delete();
        }

        $this->auditService->log(
            'logs_archived',
            null,
            'SystemLog',
            null,
            [],
            [
                'archived_count' => $archivedCount,
                'archive_file' => $archiveFilename,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]
        );

        return [
            'archived' => $archivedCount,
            'file' => $archiveFilename,
            'path' => $archivePath,
            'message' => "Archived {$archivedCount} logs to {$archiveFilename}",
        ];
    }

    /**
     * Get archive statistics
     */
    public function getArchiveStats(): array
    {
        $totalLogs = SystemLog::count();
        $oldestLog = SystemLog::oldest('created_at')->first();
        $newestLog = SystemLog::latest('created_at')->first();

        // Count logs by severity
        $severityCounts = SystemLog::selectRaw('COALESCE(severity, "INFO") as severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        // Count logs by month
        $monthlyCounts = SystemLog::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        // Get archive files
        $archiveDir = storage_path('app/archives');
        $archiveFiles = [];
        if (is_dir($archiveDir)) {
            $files = glob($archiveDir.'/system_logs_archive_*.json');
            if ($files === false) {
                $files = [];
            }

            foreach ($files as $file) {
                $size = filesize($file);
                $modifiedAt = filemtime($file);

                if ($size === false || $modifiedAt === false) {
                    continue;
                }

                $archiveFiles[] = [
                    'filename' => basename($file),
                    'size' => $this->formatBytes($size),
                    'created' => date('Y-m-d H:i:s', $modifiedAt),
                ];
            }
        }

        return [
            'total_logs' => $totalLogs,
            'oldest_log_date' => $oldestLog?->created_at?->toDateTimeString(),
            'newest_log_date' => $newestLog?->created_at?->toDateTimeString(),
            'severity_counts' => $severityCounts,
            'monthly_counts' => $monthlyCounts,
            'archive_files' => $archiveFiles,
            'retention_days' => $this->defaultRetentionDays,
        ];
    }

    /**
     * Clean up old archive files (older than 10 years by default)
     */
    public function cleanupOldArchives(int $daysToKeep = 3650): int
    {
        $archiveDir = storage_path('app/archives');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $deletedCount = 0;

        if (is_dir($archiveDir)) {
            $files = glob($archiveDir.'/system_logs_archive_*.json');
            if ($files === false) {
                $files = [];
            }

            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, $precision).' '.$units[$pow];
    }
}
