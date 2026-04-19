<?php

namespace App\Console\Commands;

use App\Services\EodReconciliationService;
use App\Services\ExportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Generate EOD Reconciliation Report
 *
 * Artisan command to generate End-of-Day reconciliation reports.
 * Scheduled to run at end of day for daily reconciliation.
 */
class GenerateEodReconciliation extends Command
{
    protected $signature = 'report:eod
        {--date= : Specific date (Y-m-d), defaults to yesterday}
        {--branch= : Optional branch ID filter}
        {--counter= : Optional specific counter ID}
        {--format= : Output format (pdf|json|csv), defaults to pdf}';

    protected $description = 'Generate End-of-Day reconciliation report';

    public function handle(EodReconciliationService $eodService, ExportService $exportService): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $counterId = $this->option('counter') ? (int) $this->option('counter') : null;
        $format = $this->option('format') ?? 'pdf';

        $this->info("Generating EOD reconciliation report for {$date}...");

        if ($branchId) {
            $this->info("Branch filter: {$branchId}");
        }

        if ($counterId) {
            $this->info("Counter filter: {$counterId}");
        }

        try {
            $carbonDate = Carbon::parse($date);
            $report = $eodService->generateReconciliationReport($carbonDate, $branchId, $counterId);

            $filename = "EOD-Reconciliation-{$date}";
            if ($counterId) {
                $filename .= "-Counter-{$counterId}";
            }

            if ($format === 'json') {
                $filename .= '.json';
                $path = $exportService->toJSON($report, $filename);
            } elseif ($format === 'csv') {
                $filename .= '.csv';
                $path = $exportService->toCSV($report, $filename);
            } else {
                // PDF - generate and save
                $path = $this->generatePdf($report, $filename);
                $filename .= '.pdf';
            }

            $this->info("EOD reconciliation report generated: {$path}");
            $this->info('Report type: '.$report['report_type']);
            $this->info('Variance status: '.$report['variance_status']['status']);

            return 0;

        } catch (\Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Generate PDF report and save to storage.
     */
    private function generatePdf(array $report, string $filename): string
    {
        $pdf = \PDF::loadView('reports.eod-reconciliation', [
            'report' => $report,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'date' => $report['date'] ?? now()->toDateString(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $path = storage_path('app/reports/'.$filename.'.pdf');

        // Ensure directory exists
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}
