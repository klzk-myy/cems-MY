<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use App\Services\ReportingService;
use Illuminate\Console\Command;

class GenerateMonthlyLCTR extends Command
{
    protected $signature = 'report:lctr {--month= : Specific month (Y-m), defaults to previous month}';

    protected $description = 'Generate monthly Cash Transaction Report (CTR) for transactions >= RM50,000';

    public function handle(ReportingService $reportingService): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');

        $this->info("Generating Cash Transaction Report for {$month}...");

        try {
            $filepath = $reportingService->generateLCTR($month);

            ReportGenerated::create([
                'report_type' => 'LCTR',
                'period_start' => now()->parse($month)->startOfMonth(),
                'period_end' => now()->parse($month)->endOfMonth(),
                'generated_by' => 1,
                'generated_at' => now(),
                'file_format' => 'CSV',
                'status' => 'Generated',
            ]);

            $this->info("LCTR generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
