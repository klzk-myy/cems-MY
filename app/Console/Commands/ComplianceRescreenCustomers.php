<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\UnifiedSanctionScreeningService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ComplianceRescreenCustomers extends Command
{
    protected $signature = 'compliance:rescreen 
                            {--days=30 : Days since last screening}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Rescreen all customers against sanctions lists for BNM compliance
                            
This command should be run monthly per BNM AML/CFT requirements.
Customers who have not been screened in the specified number of days will be rescreened.';

    public function handle(UnifiedSanctionScreeningService $screeningService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('Starting customer sanctions rescreening...');
        $this->info("Screening customers not screened in last {$days} days".($dryRun ? ' (DRY RUN)' : ''));

        $cutoffDate = Carbon::now()->subDays($days);

        $customers = Customer::where(function ($q) use ($cutoffDate) {
            $q->where('last_sanction_screened_at', '<', $cutoffDate)
                ->orWhereNull('last_sanction_screened_at');
        })->where('is_active', true)->get();

        $total = $customers->count();

        if ($total === 0) {
            $this->info('No customers require rescreening.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} customers to rescreen.");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $screened = 0;
        $matches = 0;
        $errors = 0;

        foreach ($customers as $customer) {
            try {
                if ($dryRun) {
                    $this->line("\n  Would screen: {$customer->full_name} ({$customer->id})");
                    $progressBar->advance();

                    continue;
                }

                $results = $screeningService->screenName($customer->full_name);

                if (! empty($results)) {
                    $matches++;
                    $this->warn("\n  MATCH: {$customer->full_name} - Score: {$results[0]['match_score']}");

                    foreach ($results as $result) {
                        $this->line("    Entry: {$result['entity_name']} ({$result['match_type']})");
                    }

                    $customer->update([
                        'last_sanction_screened_at' => now(),
                    ]);
                } else {
                    $customer->update([
                        'last_sanction_screened_at' => now(),
                    ]);
                }

                $screened++;
                $progressBar->advance();
            } catch (\Exception $e) {
                $errors++;
                Log::error("Sanctions screening error for customer {$customer->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Rescreening complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total customers screened', $screened],
                ['New matches found', $matches],
                ['Errors encountered', $errors],
            ]
        );

        if ($errors > 0) {
            $this->warn("Completed with {$errors} errors. Check logs for details.");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
