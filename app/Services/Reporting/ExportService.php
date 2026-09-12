<?php

namespace App\Services\Reporting;

use App\Enums\ReportGeneratedStatus;
use App\Exceptions\Domain\ReportValidationException;
use App\Models\ReportGenerated;
use App\Models\User;
use App\Notifications\ReportEmailNotification;
use App\Services\System\NotificationDispatcher;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

class ExportService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = storage_path('app/reports');
    }

    public function toCSV(array $data, string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->basePath.'/'.$filename;

        if (! file_exists($this->basePath) && ! mkdir($this->basePath, 0755, true) && ! is_dir($this->basePath)) {
            throw new ReportValidationException("Failed to create reports directory: {$this->basePath}");
        }

        $handle = fopen($path, 'w+');
        if (! $handle) {
            throw new ReportValidationException("Failed to open CSV file for writing: {$path}");
        }

        if (! empty($data)) {
            fputcsv($handle, self::sanitizeRow(array_keys($data[0])));

            foreach ($data as $row) {
                fputcsv($handle, self::sanitizeRow(array_values($row)));
            }
        }

        fclose($handle);

        return $path;
    }

    /**
     * Encode the provided data structure as JSON and store it following the
     * same conventions as toCSV() (sanitized filename under app/reports),
     * returning the absolute file path.
     *
     * @param  mixed  $data  Any JSON-encodable structure (typically array)
     */
    public function toJson(mixed $data, string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->basePath.'/'.$filename;

        if (! file_exists($this->basePath) && ! mkdir($this->basePath, 0755, true) && ! is_dir($this->basePath)) {
            throw new ReportValidationException("Failed to create reports directory: {$this->basePath}");
        }

        $bytes = file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if ($bytes === false) {
            throw new ReportValidationException("Failed to open JSON file for writing: {$path}");
        }

        return $path;
    }

    /**
     * Neutralize spreadsheet formula injection (CSV injection, OWASP).
     * Cells beginning with =, +, -, @, tab, or CR are interpreted by Excel and
     * Google Sheets as formulas. Prefixing them with a single quote forces them
     * to be treated as plain text.
     *
     * Cells that parse as plain decimal numbers - including negative monetary
     * values such as "-1234.50" - are exempt from prefixing: a leading "-" is
     * how negative amounts are rendered in BNM exports, not an injection
     * vector. Non-numeric cells starting with "-" keep the guard.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map(function (mixed $value): mixed {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
                return $value;
            }

            $first = $value[0];

            if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$value;
            }

            return $value;
        }, $row);
    }

    public function toPDF(array $data, string $template, string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->basePath.'/'.$filename;

        if (! file_exists($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        $pdf = \PDF::loadView($template, ['data' => $data]);
        $pdf->save($path);

        return $path;
    }

    public function toExcel(array $data, string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->basePath.'/'.$filename;

        if (! file_exists($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        $export = new class($data) implements FromArray
        {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        };

        // Store directly to the reports subdirectory using the full path
        Excel::store($export, 'reports/'.$filename, 'local');

        return $path;
    }

    public function emailReport(string $to, string $subject, string $filePath, string $reportType = ''): bool
    {
        $user = User::where('email', $to)->first();
        NotificationDispatcher::dispatchSafe(
            $user ?? $to,
            new ReportEmailNotification($subject, $filePath),
            ['mail']
        );

        return true;
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new ReportValidationException("Invalid filename: {$filename}");
        }

        return $filename;
    }

    public function getExportPath(string $filename): string
    {
        return $this->basePath.'/'.$this->sanitizeFilename($filename);
    }

    /**
     * Delete export files older than $days — but never a file whose
     * ReportGenerated record is not yet Archived. Cleanup runs at 90 days
     * while archival copies at 12 months, so without this guard the files
     * aged 90d–12mo would be destroyed before they could ever be archived
     * (BNM 7-year retention).
     */
    public function cleanupOldReports(int $days = 90): int
    {
        $cutoff = now()->subDays($days);
        $deleted = 0;

        // file_path may be absolute or storage-relative; basename matching
        // covers both forms.
        $unarchivedBasenames = ReportGenerated::where('status', '!=', ReportGeneratedStatus::Archived->value)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->map(fn ($p) => basename((string) $p))
            ->flip()
            ->all();

        $files = glob($this->basePath.'/*');
        if ($files === false) {
            $files = [];
        }

        foreach ($files as $file) {
            if (! is_file($file) || filemtime($file) >= $cutoff->timestamp) {
                continue;
            }

            if (isset($unarchivedBasenames[basename($file)])) {
                continue;
            }

            if (unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
