<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ArchiveReports extends Command
{
    protected $signature = 'reports:archive {--months=12 : Archive reports older than N months}';

    protected $description = 'Archive generated reports for regulatory compliance (BNM 7-year retention)';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoffDate = now()->subMonths($months);

        $this->info("Archiving reports generated before {$cutoffDate->toDateString()}...");

        $reportsToArchive = ReportGenerated::where('generated_at', '<', $cutoffDate)
            ->where('status', '!=', 'Archived')
            ->get();

        $count = 0;
        foreach ($reportsToArchive as $report) {
            $report->update(['status' => 'Archived']);
            $count++;
        }

        $this->info("Archived {$count} report records.");

        $archiveDir = 'archives/'.now()->format('Y/m');
        if (! Storage::exists($archiveDir)) {
            Storage::makeDirectory($archiveDir);
        }

        $this->info("Archive directory: {$archiveDir}");

        return 0;
    }
}
