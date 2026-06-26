<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReportType;
use App\Models\ReportGenerated;

trait HasReportFormatting
{
    protected function createReportRecord(ReportType $type, string $periodStart, string $periodEnd, string $format = 'CSV'): ReportGenerated
    {
        return ReportGenerated::create([
            'report_type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => auth()->id() ?? 1,
            'generated_at' => now(),
            'file_format' => $format,
            'status' => 'Generated',
        ]);
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
