<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryFailedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:retry-failed
                            {--queue= : Filter by specific queue}
                            {--limit=50 : Maximum number of jobs to retry}
                            {--all : Retry all failed jobs (ignores limit)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed queue jobs with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = DB::table('failed_jobs');

        if ($this->option('queue')) {
            $query->where('queue', $this->option('queue'));
        }

        $totalFailed = $query->count();

        if ($totalFailed === 0) {
            $this->info('No failed jobs found.');

            return 0;
        }

        $limit = $this->option('all') ? $totalFailed : (int) $this->option('limit');
        $jobsToRetry = $query->limit($limit)->pluck('id');

        $this->warn("Found {$totalFailed} failed jobs.");
        $this->info("Will retry {$jobsToRetry->count()} job(s).");

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $retried = 0;
        $failed = 0;

        foreach ($jobsToRetry as $jobId) {
            try {
                $this->call('queue:retry', ['id' => $jobId]);
                $retried++;
            } catch (\Exception $e) {
                $this->error("Failed to retry job {$jobId}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info('Retry operation complete:');
        $this->info("  - Retried: {$retried}");
        $this->info("  - Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
