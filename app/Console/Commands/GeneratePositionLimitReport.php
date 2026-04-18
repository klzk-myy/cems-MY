<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use App\Services\ReportingService;
use Illuminate\Console\Command;

class GeneratePositionLimitReport extends Command
{
    protected $signature = 'report:position-limit';

    protected $description = 'Generate daily position limit utilization report';

    public function handle(ReportingService $reportingService): int
    {
        $this->info('Generating Position Limit Report...');

        try {
            $filepath = $reportingService->generatePositionLimitCsv();

            ReportGenerated::create([
                'report_type' => 'PLR',
                'period_start' => now()->startOfDay(),
                'period_end' => now()->endOfDay(),
                'generated_by' => 1,
                'generated_at' => now(),
                'file_format' => 'CSV',
                'status' => 'Generated',
            ]);

            $this->info("Position Limit Report generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }
}
