<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonthEndCloseCommand extends Command
{
    protected $signature = 'accounting:month-end {--date= : Date for month-end close (Y-m-d), defaults to previous month} {--force : Skip pre-flight checks}';

    protected $description = 'Run month-end closing sequence with revaluation, reports, and period closing';

    public function handle(MonthEndCloseService $service): int
    {
        $dateOption = $this->option('date');

        if ($dateOption) {
            $date = Carbon::parse($dateOption);
        } else {
            $date = now()->subMonth()->endOfMonth();
        }

        $this->info("Running month-end close for {$date->toDateString()}...");

        if (! $this->option('force')) {
            $checks = $service->preFlightChecks($date);
            if (! $checks['passed']) {
                $this->error('Pre-flight checks failed:');
                foreach ($checks['failures'] as $failure) {
                    $this->line("  - {$failure}");
                }
                $this->info('Use --force to skip pre-flight checks');

                return 1;
            }
            $this->info('Pre-flight checks passed.');
        }

        try {
            $user = auth()->user() ?? User::first();
            $results = $service->runMonthEndClosing($date, $user);

            $this->info('Month-end close completed successfully.');
            $this->line('Revaluation: '.$results['revaluation']['positions_updated'].' positions, Net P&L: '.$results['revaluation']['net_pnl']);
            $this->line('Reports: '.json_encode($results['reports']));
            $this->line('Period closed: '.$results['period']['period_code']);

            return 0;
        } catch (\Exception $e) {
            $this->error('Month-end close failed: '.$e->getMessage());

            return 1;
        }
    }
}
