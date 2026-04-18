<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use App\Services\ReportingService;
use Illuminate\Console\Command;

class GenerateMonthlyLMCA extends Command
{
    protected $signature = 'report:lmca {--month= : Specific month (Y-m), defaults to previous month}';

    protected $description = 'Generate monthly BNM Form LMCA report';

    public function handle(ReportingService $reportingService): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');

        $this->info("Generating BNM Form LMCA for {$month}...");

        try {
            $filepath = $reportingService->generateFormLMCACsv($month);

            ReportGenerated::create([
                'report_type' => 'LMCA',
                'period_start' => now()->parse($month)->startOfMonth(),
                'period_end' => now()->parse($month)->endOfMonth(),
                'generated_by' => 1,
                'generated_at' => now(),
                'file_format' => 'CSV',
                'status' => 'Generated',
            ]);

            $this->info("Form LMCA generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
