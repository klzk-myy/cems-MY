<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Services\AuditService;
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
}
