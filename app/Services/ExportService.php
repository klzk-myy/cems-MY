<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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
        $path = $this->basePath.'/'.$filename;

        if (! file_exists($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        $handle = fopen($path, 'w+');

        if (! empty($data)) {
            fputcsv($handle, array_keys($data[0]));

            foreach ($data as $row) {
                fputcsv($handle, array_values($row));
            }
        }

        fclose($handle);

        return $path;
    }

    public function toPDF(array $data, string $template, string $filename): string
    {
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
        try {
            \Mail::raw($subject, function ($message) use ($to, $subject, $filePath) {
                $message->to($to)
                    ->subject($subject)
                    ->attach($filePath);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to email report', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getExportPath(string $filename): string
    {
        return $this->basePath.'/'.$filename;
    }

    public function cleanupOldReports(int $days = 90): int
    {
        $cutoff = now()->subDays($days);
        $deleted = 0;

        $files = glob($this->basePath.'/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff->timestamp) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
