<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;

trait HasReportFormatting
{
    protected function createReportRecord(
        ReportType $reportType,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $status = 'Generated',
        string $format = 'CSV'
    ): ReportGenerated {
        return app(ReportingService::class)->recordGeneratedReport(
            $reportType,
            $periodStart,
            $periodEnd,
            $status,
            $format
        );
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
        file_put_contents($filepath, $csvContent);
    }
}
