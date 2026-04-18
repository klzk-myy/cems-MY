<?php

/**
 * CLI Test Runner - Saves results to database
 * Usage: php artisan test:run [suite] [--options]
 * Example: php artisan test:run Navigation
 * Example: php artisan test:run full --verbose
 */

namespace App\Console\Commands;

use App\Services\TestRunnerService;
use Illuminate\Console\Command;

class RunTestsCommand extends Command
{
    protected $signature = 'test:run 
                            {suite=full : Test suite to run (full, Navigation, Transaction, User, Branch, Api, Compliance, Accounting)}
                            {--options= : Additional options for php artisan test}';

    protected $description = 'Run tests and save results to database';

    protected TestRunnerService $testRunner;

    public function __construct(TestRunnerService $testRunner)
    {
        parent::__construct();
        $this->testRunner = $testRunner;
    }

    public function handle(): int
    {
        $suite = $this->argument('suite');
        $options = [];

        if ($this->option('options')) {
            $options = explode(' ', $this->option('options'));
        }

        $this->info("Running test suite: {$suite}");
        $this->info('This may take a few minutes...\n');

        $startTime = microtime(true);

        try {
            $result = $this->testRunner->runTests($suite, $options);

            $duration = round(microtime(true) - $startTime, 2);

            // Display results
            $this->newLine();
            $this->info('========================================');
            $this->info('Test Run Completed');
            $this->info('========================================');
            $this->info("Run ID: {$result->run_id}");
            $this->info("Suite: {$result->test_suite}");
            $this->info("Status: {$result->status_label}");
            $this->info("Tests: {$result->passed}/{$result->total_tests} passed");
            $this->info("Pass Rate: {$result->pass_rate}%");
            $this->info("Duration: {$result->formatted_duration}");
            $this->info("DB Duration: {$duration}s");
            $this->info('========================================');

            if ($result->status === 'failed') {
                $this->error("\nFailed Tests: {$result->failed}");
                if (! empty($result->failures)) {
                    $this->error("\nFailures:");
                    foreach ($result->failures as $i => $failure) {
                        $this->error(($i + 1).'. '.($failure['test'] ?? 'Unknown'));
                    }
                }

                return 1;
            }

            $this->info("\nView details at: ".route('test-results.show', $result));

            return 0;

        } catch (\Exception $e) {
            $this->error('Test run failed: '.$e->getMessage());

            return 1;
        }
    }
}
