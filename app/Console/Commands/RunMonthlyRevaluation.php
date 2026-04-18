<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use App\Services\RevaluationService;
use Illuminate\Console\Command;

class RunMonthlyRevaluation extends Command
{
    protected $signature = 'revaluation:run {--force : Force run even if not month-end} {--till=MAIN : Till ID to revalue}';

    protected $description = 'Run monthly currency revaluation';

    public function handle(RevaluationService $service, ExportService $exportService): int
    {
        $isMonthEnd = now()->isLastOfMonth();

        if (! $isMonthEnd && ! $this->option('force')) {
            $this->info('Not month-end. Use --force to run manually.');

            return 0;
        }

        $this->info('Running month-end revaluation...');

        try {
            $results = $service->runRevaluationWithJournal();

            $filename = 'Revaluation_'.now()->format('Y-m').'.csv';
            $path = $exportService->toCSV($results['results'], $filename);
            $results['report_path'] = $path;

            $service->sendRevaluationNotification($results);

            $this->info("Revaluation complete. {$results['positions_updated']} positions updated.");
            $this->info("Net P&L: {$results['net_pnl']}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Revaluation failed: '.$e->getMessage());

            return 1;
        }
    }
}
