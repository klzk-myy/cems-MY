<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SanctionsPruneCommand extends Command
{
    protected $signature = 'sanctions:prune
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune sanctions download temp files and archives past their retention windows';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $archiveDays = (int) config('sanctions.download.archive_retention_days', 30);
        $tempHours = (int) config('sanctions.download.temp_retention_hours', 24);

        $archiveDir = (string) config('sanctions.download.archive_directory', storage_path('app/archive/sanctions'));
        $tempDir = (string) config('sanctions.download.temp_directory', storage_path('app/temp/sanctions'));

        [$archived, $archivedBytes] = $this->pruneDirectory($archiveDir, now()->subDays($archiveDays)->getTimestamp(), $dryRun, 'archive');
        [$temped, $tempBytes] = $this->pruneDirectory($tempDir, now()->subHours($tempHours)->getTimestamp(), $dryRun, 'temp');

        $this->info(sprintf(
            '%s %d archive file(s) older than %d days (%s), %d temp file(s) older than %d hours (%s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $archived,
            $archiveDays,
            $this->formatBytes($archivedBytes),
            $temped,
            $tempHours,
            $this->formatBytes($tempBytes)
        ));

        if ($archived + $temped > 0) {
            Log::info('Sanctions storage pruned', [
                'archive_files' => $archived,
                'archive_bytes' => $archivedBytes,
                'temp_files' => $temped,
                'temp_bytes' => $tempBytes,
                'dry_run' => $dryRun,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [files removed, bytes freed]
     */
    protected function pruneDirectory(string $dir, int $olderThan, bool $dryRun, string $label): array
    {
        if (! is_dir($dir)) {
            return [0, 0];
        }

        $files = 0;
        $bytes = 0;

        foreach (glob($dir.'/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $mtime = filemtime($path);
            if ($mtime === false || $mtime >= $olderThan) {
                continue;
            }

            $size = filesize($path) ?: 0;

            if ($dryRun) {
                $this->line("  [dry-run] {$label}: {$path} (".$this->formatBytes($size).')');
            } elseif (! unlink($path)) {
                $this->warn("  Could not delete {$label} file: {$path}");

                continue;
            }

            $files++;
            $bytes += $size;
        }

        return [$files, $bytes];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
