<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Services\AuditService;
use App\Services\Reporting\CsvReportWriter;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;

trait HasReportFormatting
{
    protected function createReportRecord(
        ReportType $reportType,
        Carbon $periodStart,
        Carbon $periodEnd,
        ReportGeneratedStatus $status = ReportGeneratedStatus::Generated,
        string $format = 'CSV'
    ): ReportGenerated {
        $record = app(ReportingService::class)->recordGeneratedReport(
            $reportType,
            $periodStart,
            $periodEnd,
            $status,
            $format
        );

        $actor = auth()->user();

        app(AuditService::class)->logRegulatoryReportEvent('regulatory_report_generated', $record->id, [
            'user_id' => auth()->id() ?? config('cems.system_user_id', 1),
            'actor' => $actor !== null ? $actor->username : 'system',
            'source' => 'scheduled_command',
            'new_values' => [
                'report_type' => $reportType->value,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'file_id' => $record->id,
            ],
        ]);

        return $record;
    }

    protected function getReportFilename(ReportType $type, string $suffix): string
    {
        return $type->filenameKey().'_'.now()->format('Y-m-d').'_'.$suffix.'.csv';
    }

    protected function getReportPath(string $filename): string
    {
        return storage_path('app/reports/'.$filename);
    }

    protected function saveReportCsv(string $filepath, string $csvContent): void
    {
        $dir = dirname($filepath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Re-emit through fputcsv so cells are properly quoted and every cell
        // passes CsvReportWriter's spreadsheet formula-injection guard; the
        // caller-provided text is plain comma/newline separated rows. Column
        // order is preserved exactly as the caller laid it out.
        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Failed to open report file for writing: {$filepath}");
        }

        try {
            foreach (preg_split('/\r?\n/', rtrim($csvContent, "\r\n")) as $line) {
                if ($line === '') {
                    continue;
                }

                fputcsv($handle, app(CsvReportWriter::class)->sanitizeRow(str_getcsv($line)));
            }
        } finally {
            fclose($handle);
        }
    }
}
