<?php

namespace App\Console\Commands;

use App\Models\ReportGenerated;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateQuarterlyLVR extends Command
{
    protected $signature = 'report:qlvr {--quarter= : Specific quarter (Y-Qn), defaults to previous quarter}';

    protected $description = 'Generate quarterly large value transaction report';

    public function handle(ReportingService $reportingService): int
    {
        $quarter = $this->option('quarter') ?? $this->getPreviousQuarter();

        $this->info("Generating Quarterly Large Value Report for {$quarter}...");

        try {
            $filepath = $reportingService->generateQuarterlyLargeValueCsv($quarter);

            ReportGenerated::create([
                'report_type' => 'QLVR',
                'period_start' => $this->getQuarterStart($quarter),
                'period_end' => $this->getQuarterEnd($quarter),
                'generated_by' => 1,
                'generated_at' => now(),
                'file_format' => 'CSV',
                'status' => 'Generated',
            ]);

            $this->info("Quarterly LVR generated: {$filepath}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }

    protected function getPreviousQuarter(): string
    {
        $now = now();
        $q = ceil($now->format('n') / 3);
        $y = $now->year;

        if ($q === 1) {
            return ($y - 1).'-Q4';
        }

        return $y.'-Q'.($q - 1);
    }

    protected function getQuarterStart(string $quarter): Carbon
    {
        $parts = explode('-', $quarter);
        $year = (int) $parts[0];
        $q = (int) substr($parts[1], 1);
        $startMonth = (($q - 1) * 3) + 1;

        return Carbon::create($year, $startMonth, 1)->startOfMonth();
    }

    protected function getQuarterEnd(string $quarter): Carbon
    {
        return $this->getQuarterStart($quarter)->copy()->addMonths(3)->subDay()->endOfDay();
    }
}
