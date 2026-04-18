<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SanctionsDownloadService
{
    protected string $tempDirectory;

    protected int $timeout;

    public function __construct()
    {
        $this->tempDirectory = config('sanctions.download.temp_directory', storage_path('app/temp/sanctions'));
        $this->timeout = config('sanctions.download.timeout', 300);
    }

    /**
     * Download a sanctions list from URL with retry logic.
     *
     * @param  string  $url  Source URL
     * @param  string  $filename  Target filename
     * @param  string  $format  Expected format (XML, CSV, JSON)
     * @param  int  $retryAttempts  Number of retry attempts
     * @return array{success: bool, filepath: string|null, checksum: string|null, error: string|null, format_valid: bool}
     */
    public function download(
        string $url,
        string $filename,
        string $format = 'XML',
        int $retryAttempts = 3
    ): array {
        $this->ensureTempDirectoryExists();

        $filepath = $this->tempDirectory.'/'.$filename;
        $lastError = null;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withUserAgent(config('sanctions.download.user_agent', 'CEMS-MY/1.0'))
                    ->get($url);

                if (! $response->successful()) {
                    $lastError = "HTTP {$response->status()}: Failed to download from {$url}";
                    Log::warning("Sanctions download attempt {$attempt} failed", [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

                    if ($attempt < $retryAttempts) {
                        sleep(config('sanctions.sources.un.retry_delay', 60));
                    }

                    continue;
                }

                $content = $response->body();

                // Validate format
                $formatValid = $this->validateFormat($content, $format);

                if (! $formatValid) {
                    return [
                        'success' => false,
                        'filepath' => null,
                        'checksum' => null,
                        'error' => "Downloaded content is not valid {$format}",
                        'format_valid' => false,
                    ];
                }

                // Save file
                file_put_contents($filepath, $content);

                // Calculate checksum
                $checksum = hash('sha256', $content);

                Log::info('Sanctions list downloaded successfully', [
                    'url' => $url,
                    'filepath' => $filepath,
                    'size' => strlen($content),
                    'checksum' => $checksum,
                ]);

                return [
                    'success' => true,
                    'filepath' => $filepath,
                    'checksum' => $checksum,
                    'error' => null,
                    'format_valid' => true,
                ];

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("Sanctions download attempt {$attempt} failed with exception", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $retryAttempts) {
                    sleep(config('sanctions.sources.un.retry_delay', 60));
                }
            }
        }

        Log::error("Sanctions download failed after {$retryAttempts} attempts", [
            'url' => $url,
            'last_error' => $lastError,
        ]);

        return [
            'success' => false,
            'filepath' => null,
            'checksum' => null,
            'error' => $lastError ?? 'Unknown error',
            'format_valid' => false,
        ];
    }

    /**
     * Validate downloaded content matches expected format.
     */
    protected function validateFormat(string $content, string $format): bool
    {
        return match ($format) {
            'XML' => $this->validateXml($content),
            'JSON' => $this->validateJson($content),
            'CSV' => $this->validateCsv($content),
            default => true,
        };
    }

    protected function validateXml(string $content): bool
    {
        $previousValue = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content);
        libxml_use_internal_errors($previousValue);

        return $doc !== false;
    }

    protected function validateJson(string $content): bool
    {
        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function validateCsv(string $content): bool
    {
        // Basic CSV validation - check for comma-separated structure
        $lines = explode("\n", $content);
        if (count($lines) < 2) {
            return false;
        }

        $firstLine = $lines[0];

        return str_contains($firstLine, ',') || str_contains($firstLine, "\t");
    }

    /**
     * Archive the downloaded file.
     */
    public function archiveFile(string $filepath, string $listType): ?string
    {
        $archiveDir = config('sanctions.download.archive_directory', storage_path('app/archive/sanctions'));

        if (! is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $filename = basename($filepath);
        $archivePath = $archiveDir.'/'.$listType.'_'.date('Y-m-d_His').'_'.$filename;

        if (copy($filepath, $archivePath)) {
            Log::info('Sanctions file archived', [
                'source' => $filepath,
                'archive' => $archivePath,
            ]);

            return $archivePath;
        }

        return null;
    }

    /**
     * Clean up old archive files.
     */
    public function cleanupArchives(int $days = 30): int
    {
        $archiveDir = config('sanctions.download.archive_directory', storage_path('app/archive/sanctions'));

        if (! is_dir($archiveDir)) {
            return 0;
        }

        $cutoff = time() - ($days * 86400);
        $deleted = 0;

        foreach (glob($archiveDir.'/*') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        Log::info("Cleaned up {$deleted} old sanctions archive files");

        return $deleted;
    }

    protected function ensureTempDirectoryExists(): void
    {
        if (! is_dir($this->tempDirectory)) {
            mkdir($this->tempDirectory, 0755, true);
        }
    }
}
