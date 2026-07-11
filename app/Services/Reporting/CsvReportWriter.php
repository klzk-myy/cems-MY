<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\Storage;

class CsvReportWriter
{
    /**
     * Write a simple CSV report with a single header row followed by data rows.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function write(string $filename, array $headers, array $rows): string
    {
        return $this->writeToDisk($filename, function ($csv) use ($headers, $rows) {
            fputcsv($csv, $headers);

            foreach ($rows as $row) {
                fputcsv($csv, $row);
            }
        });
    }

    /**
     * Write a CSV report with leading title rows, a blank separator row,
     * a header row, and then data rows.
     *
     * @param  array<int, array<int, mixed>>  $titleRows
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function writeWithTitleRows(string $filename, array $titleRows, array $headers, array $rows): string
    {
        return $this->writeToDisk($filename, function ($csv) use ($titleRows, $headers, $rows) {
            foreach ($titleRows as $titleRow) {
                fputcsv($csv, $titleRow);
            }

            fputcsv($csv, []);
            fputcsv($csv, $headers);

            foreach ($rows as $row) {
                fputcsv($csv, $row);
            }
        });
    }

    /**
     * @param  callable(resource): void  $writer
     */
    protected function writeToDisk(string $filename, callable $writer): string
    {
        if (! Storage::exists('reports')) {
            Storage::makeDirectory('reports');
        }

        $filepath = "reports/{$filename}";
        $csv = fopen(Storage::path($filepath), 'w');

        if (! $csv) {
            throw new \RuntimeException("Failed to open report file for writing: {$filepath}");
        }

        $writer($csv);
        fclose($csv);

        return $filepath;
    }
}
