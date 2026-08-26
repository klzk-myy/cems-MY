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
            $pending = Queue::size('default');

            // Positive evidence only: Horizon master supervisors refresh
            // their "horizon:master:*" heartbeat keys every few seconds
            // (15s TTL), so their presence proves workers ran recently.
            // An empty queue alone is NOT evidence of healthy workers.
            if ($this->horizonMasterHeartbeatExists()) {
                $this->checks['workers'] = ['status' => 'OK', 'message' => 'Workers appear to be running'];
            } elseif ($pending > 0) {
                $this->checks['workers'] = [
                    'status' => 'WARNING',
                    'message' => "No Horizon worker heartbeats detected with {$pending} pending job(s). Check supervisor.",
                ];
            } else {
                $this->checks['workers'] = [
                    'status' => 'WARNING',
                    'message' => 'No Horizon worker heartbeats detected - Horizon appears inactive. Check supervisor.',
                ];
            }
        } catch (\Exception $e) {
            $this->checks['workers'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Detect a live Horizon master supervisor heartbeat via SCAN.
     *
     * Uses Redis SCAN (never KEYS) so the check never blocks the server,
     * bounded by a batch cap to keep it cheap on large keyspaces.
     *
     * Note: phpredis applies OPT_PREFIX transparently to KEYS but NOT to
     * SCAN MATCH patterns, so the configured prefix must be included in the
     * pattern explicitly. The initial cursor must be null - a literal "0"
     * makes phpredis short-circuit the iteration.
     */
    private function horizonMasterHeartbeatExists(): bool
    {
        $connection = Redis::connection();
        $client = $connection->client();

        if (! $client instanceof \Redis) {
            return false;
        }

        // phpredis applies OPT_PREFIX transparently to KEYS but NOT to SCAN
        // MATCH patterns, so the configured prefix must be included in the
        // pattern explicitly. The initial cursor must be null - a literal "0"
        // makes phpredis short-circuit the iteration.
        $prefix = (string) $client->getOption(\Redis::OPT_PREFIX);

        $cursor = null;

        for ($batches = 0; $batches < 100; $batches++) {
            $result = $client->scan($cursor, $prefix.'horizon:master:*', 200);

            // PhpRedisConnection::scan returns false when the full keyspace
            // was scanned and nothing matched, else [cursor, keys].
            if ($result === false) {
                return false;
            }

            [$cursor, $keys] = $result;

            foreach ((array) $keys as $key) {
                if ($key !== '') {
                    return true;
                }
            }

            if ((int) $cursor === 0) {
                return false;
            }
        }

        return false;
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
