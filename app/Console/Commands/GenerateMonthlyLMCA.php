<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasReportFormatting;
use App\Services\Reporting\ReportingService;
use Illuminate\Console\Command;

class GenerateMonthlyLMCA extends Command
{
    use HasReportFormatting;

    protected $signature = 'report:lmca {--month= : Specific month (Y-m), defaults to previous month}';

    protected $description = 'Generate monthly BNM Form LMCA report';

    public function handle(ReportingService $reportingService): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');

        $this->info("Generating BNM Form LMCA for {$month}...");

        try {
            $filepath = $reportingService->generateFormLMCACsv($month);

            $this->createReportRecord('LMCA', now()->parse($month)->startOfMonth(), now()->parse($month)->endOfMonth());

            $this->info("Form LMCA generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
