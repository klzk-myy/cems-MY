<?php

namespace App\Services;

use App\Models\SystemLog;
use Carbon\Carbon;

class LogRotationService
{
    /**
     * Default retention period in days
     */
    protected int $defaultRetentionDays = 90;

    /**
     * Archive logs older than retention period
     */
    public function archiveOldLogs(?int $retentionDays = null): array
    {
        $retentionDays = $retentionDays ?? $this->defaultRetentionDays;
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $logsToArchive = SystemLog::where('created_at', '<', $cutoffDate)->get();

        if ($logsToArchive->isEmpty()) {
            return [
                'archived' => 0,
                'file' => null,
                'message' => 'No logs to archive',
            ];
        }

        // Create archive filename
        $archiveFilename = 'system_logs_archive_'.now()->format('Y_m_d_His').'.json';
        $archivePath = storage_path('app/archives/'.$archiveFilename);

        // Ensure directory exists
        if (! file_exists(dirname($archivePath))) {
            mkdir(dirname($archivePath), 0755, true);
        }

        // Write logs to JSON file
        $archiveData = $logsToArchive->map(function ($log) {
            return [
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
            ];
        });

        file_put_contents($archivePath, json_encode($archiveData, JSON_PRETTY_PRINT));

        // Delete archived logs from database
        $archivedCount = SystemLog::where('created_at', '<', $cutoffDate)->delete();

        // Log the archive action
        SystemLog::create([
            'user_id' => null,
            'action' => 'logs_archived',
            'severity' => 'INFO',
            'entity_type' => 'SystemLog',
            'new_values' => [
                'archived_count' => $archivedCount,
                'archive_file' => $archiveFilename,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->toDateString(),
            ],
            'ip_address' => request()->ip(),
        ]);

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
            foreach ($files as $file) {
                $archiveFiles[] = [
                    'filename' => basename($file),
                    'size' => $this->formatBytes(filesize($file)),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
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
     * Clean up old archive files (older than 2 years)
     */
    public function cleanupOldArchives(int $daysToKeep = 730): int
    {
        $archiveDir = storage_path('app/archives');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $deletedCount = 0;

        if (is_dir($archiveDir)) {
            $files = glob($archiveDir.'/system_logs_archive_*.json');
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
