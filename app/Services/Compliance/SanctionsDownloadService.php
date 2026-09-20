<?php

namespace App\Services\Compliance;

use App\Enums\SanctionSourceFormat;
use App\Services\Concerns\ValidatesContentFormat;
use App\Services\Concerns\ValidatesSanctionsUrl;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SanctionsDownloadService
{
    use ValidatesContentFormat;
    use ValidatesSanctionsUrl;

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
     * @param  SanctionSourceFormat  $format  Expected format
     * @param  int  $retryAttempts  Number of retry attempts
     * @return array{success: bool, filepath: string|null, checksum: string|null, error: string|null, format_valid: bool}
     */
    public function download(
        string $url,
        string $filename,
        SanctionSourceFormat $format = SanctionSourceFormat::Xml,
        int $retryAttempts = 3
    ): array {
        $this->validateUrl($url);
        $this->ensureTempDirectoryExists();

        $filepath = $this->tempDirectory.'/'.$filename;
        $lastError = null;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $response = $this->getFollowingSafeRedirects($url);

                if (! $response->successful()) {
                    $lastError = "HTTP {$response->status()}: Failed to download from {$url}";
                    Log::warning("Sanctions download attempt {$attempt} failed", [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

                    if ($attempt < $retryAttempts) {
                        sleep(config('sanctions.download.retry_delay', 60));
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
                        'error' => "Downloaded content is not valid {$format->value}",
                        'format_valid' => false,
                    ];
                }

                // Save file
                $bytesWritten = file_put_contents($filepath, $content);

                // Verify write was successful
                if ($bytesWritten === false || $bytesWritten !== strlen($content)) {
                    // Cleanup corrupted file
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }

                    return [
                        'success' => false,
                        'filepath' => null,
                        'checksum' => null,
                        'error' => 'Failed to write file completely',
                        'format_valid' => true,
                    ];
                }

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
                    sleep(config('sanctions.download.retry_delay', 60));
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
    protected function validateFormat(string $content, SanctionSourceFormat $format): bool
    {
        return match ($format) {
            SanctionSourceFormat::Xml => $this->validateXml($content),
            SanctionSourceFormat::Json => $this->validateJson($content),
            SanctionSourceFormat::Csv => $this->validateCsv($content),
        };
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

        $archiveFiles = glob($archiveDir.'/*');
        if ($archiveFiles === false) {
            $archiveFiles = [];
        }

        foreach ($archiveFiles as $file) {
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

    /**
     * GET a URL, following redirects manually. Each hop is re-validated
     * against the sanctions allowlist and private-IP check, so a 3xx can
     * never bounce the request to an internal or non-allowlisted address.
     * (data.opensanctions.org legitimately redirects to versioned artifact
     * URLs on the same host.)
     */
    protected function getFollowingSafeRedirects(string $url): Response
    {
        $currentUrl = $url;
        $maxHops = 5;

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            $this->validateUrl($currentUrl);

            $response = Http::timeout($this->timeout)
                ->withUserAgent(config('sanctions.download.user_agent', 'CEMS-MY/1.0'))
                ->withoutRedirecting()
                ->get($currentUrl);

            if (! $response->redirect()) {
                return $response;
            }

            $location = $response->header('Location');
            if (empty($location)) {
                return $response;
            }

            $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
        }

        throw new \RuntimeException("Too many redirects ({$maxHops}) fetching {$url}");
    }

    /**
     * Resolve a possibly-relative Location header against the request URL.
     */
    protected function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $origin = ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '')
            .(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';

        return $origin.$path.$location;
    }
}
