<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health-check
                            {--queue=default : Queue to check}
                            {--threshold=100 : Alert threshold for pending jobs}
                            {--failed-threshold=10 : Alert threshold for failed jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check queue health and worker status';

    /**
     * Health check results.
     *
     * @var array<string, array{status: string, message: string}>
     */
    private array $checks = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Running queue health check...');
        $this->newLine();

        $this->checkConnection();
        $this->checkQueueSize();
        $this->checkFailedJobs();
        $this->checkWorkerStatus();

        $this->displayResults();

        return $this->determineExitCode();
    }

    /**
     * Check Redis connection health.
     */
    private function checkConnection(): void
    {
        try {
            Redis::connection()->ping();
            $this->checks['connection'] = ['status' => 'OK', 'message' => 'Redis connection active'];
        } catch (\Exception $e) {
            $this->checks['connection'] = ['status' => 'CRITICAL', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check queue size against threshold.
     */
    private function checkQueueSize(): void
    {
        $queue = $this->option('queue');
        $threshold = $this->option('threshold');

        try {
            $size = Queue::size($queue);
            $status = $size > $threshold ? 'WARNING' : 'OK';

            $this->checks['queue_size'] = [
                'status' => $status,
                'message' => "Queue '{$queue}' has {$size} pending jobs (threshold: {$threshold})",
            ];
        } catch (\Exception $e) {
            $this->checks['queue_size'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check failed jobs count.
     */
    private function checkFailedJobs(): void
    {
        $threshold = $this->option('failed-threshold');

        try {
            $count = DB::table('failed_jobs')->count();
            $status = $count > $threshold ? 'WARNING' : 'OK';

            $this->checks['failed_jobs'] = [
                'status' => $status,
                'message' => "{$count} failed jobs (threshold: {$threshold})",
            ];
        } catch (\Exception $e) {
            $this->checks['failed_jobs'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if workers are running.
     */
    private function checkWorkerStatus(): void
    {
        try {
            // Check for queue workers by looking for reserved jobs (indicates workers are active)
            $hasWorkers = false;

            // Try to get a list of reserved jobs
            $reservedKey = 'queues:default:reserved';
            if (Redis::exists($reservedKey)) {
                $hasWorkers = true;
            }

            // Check if there are any worker heartbeats
            $horizonKey = 'horizon:*';
            $horizonKeys = Redis::keys($horizonKey);
            if (! empty($horizonKeys)) {
                $hasWorkers = true;
            }

            // If no workers detected but queues exist, check if jobs are being processed
            $pending = Queue::size('default');
            if ($pending === 0) {
                $hasWorkers = true; // Empty queue means workers may be idle but functional
            }

            if ($hasWorkers) {
                $this->checks['workers'] = ['status' => 'OK', 'message' => 'Workers appear to be running'];
            } else {
                $this->checks['workers'] = [
                    'status' => 'WARNING',
                    'message' => 'No active workers detected. Check supervisor.',
                ];
            }
        } catch (\Exception $e) {
            $this->checks['workers'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Display health check results in a table.
     */
    private function displayResults(): void
    {
        $headers = ['Check', 'Status', 'Message'];
        $rows = [];

        foreach ($this->checks as $check => $data) {
            $rows[] = [$check, $data['status'], $data['message']];
        }

        $this->table($headers, $rows);
    }

    /**
     * Determine exit code based on check results.
     */
    private function determineExitCode(): int
    {
        $hasCritical = collect($this->checks)->contains(fn ($check) => $check['status'] === 'CRITICAL');
        $hasWarning = collect($this->checks)->contains(fn ($check) => $check['status'] === 'WARNING');

        if ($hasCritical) {
            $this->error('Health check FAILED - Critical issues detected');

            return 2;
        }

        if ($hasWarning) {
            $this->warn('Health check PASSED with warnings');

            return 1;
        }

        $this->info('Health check PASSED - All systems operational');

        return 0;
    }
}
