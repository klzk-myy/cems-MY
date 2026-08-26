<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ClearStuckQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:clear-stuck
                            {--queue=default : Queue to clear}
                            {--hours=24 : Clear jobs older than N hours}
                            {--dry-run : Show what would be cleared without clearing}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency command to clear stuck/stale queue jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queue = $this->option('queue');
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->warn('⚠️  EMERGENCY QUEUE CLEAR OPERATION');
        $this->warn("This will clear jobs from '{$queue}' queue older than {$hours} hours.");
        $this->newLine();

        // Get queue contents from Redis
        $redisKey = "queues:{$queue}";
        $jobs = Redis::lrange($redisKey, 0, -1);

        if (empty($jobs)) {
            $this->info('No jobs found in queue.');

            return 0;
        }

        $stuckJobs = [];
        $cutoffTime = Carbon::now()->subHours($hours);

        foreach ($jobs as $index => $jobPayload) {
            $job = json_decode($jobPayload, true);
            $createdAt = isset($job['pushedAt'])
                ? Carbon::createFromTimestamp($job['pushedAt'])
                : (isset($job['created_at']) ? Carbon::parse($job['created_at']) : null);

            if ($createdAt && $createdAt->lt($cutoffTime)) {
                $stuckJobs[] = [
                    'index' => $index,
                    'id' => $job['id'] ?? 'unknown',
                    'displayName' => $job['displayName'] ?? ($job['job'] ?? 'unknown'),
                    'created_at' => $createdAt->toDateTimeString(),
                    // Keep the exact payload seen during the scan so removals
                    // can be verified against it (concurrent pops shift
                    // list indices).
                    'payload' => $jobPayload,
                ];
            }
        }

        if (empty($stuckJobs)) {
            $this->info('No stuck jobs found.');

            return 0;
        }

        $this->table(
            ['Index', 'Job ID', 'Job Name', 'Created At'],
            collect($stuckJobs)->map(fn ($stuckJob) => [
                'index' => $stuckJob['index'],
                'id' => $stuckJob['id'],
                'displayName' => $stuckJob['displayName'],
                'created_at' => $stuckJob['created_at'],
            ])->all()
        );

        $this->warn('Found '.count($stuckJobs).' stuck job(s).');

        if ($dryRun) {
            $this->info('Dry run mode - no changes made.');

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to REMOVE these jobs?', false)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Remove stuck jobs using LREM (safer than ltrim which affects all jobs).
        // List indices shift as we remove entries and as concurrent workers pop
        // from the head, so before each removal re-read the payload at the
        // adjusted index and only delete when it is still the exact job that
        // was identified as stuck. Otherwise skip with a warning.
        $client = Redis::connection()->client();

        if (! $client instanceof \Redis) {
            $this->error('queue:clear-stuck requires the phpredis client.');

            return 1;
        }

        $removed = 0;
        $skipped = 0;
        foreach ($stuckJobs as $stuckJob) {
            // Get the actual payload at the adjusted position
            $jobPayload = $client->lindex($redisKey, (int) $stuckJob['index'] - $removed);

            if ($jobPayload === false || $jobPayload === null) {
                $this->warn("Skipping job [{$stuckJob['id']}] - no longer present in queue.");
                $skipped++;

                continue;
            }

            if ($jobPayload !== $stuckJob['payload']) {
                $this->warn("Skipping job [{$stuckJob['id']}] - queue shifted under us (concurrent modification).");
                $skipped++;

                continue;
            }

            // Count of 1 removes only this occurrence, never duplicates.
            $result = $client->lrem($redisKey, $jobPayload, 1);
            if ($result > 0) {
                $removed++;
            } else {
                $this->warn("Skipping job [{$stuckJob['id']}] - could not be removed.");
                $skipped++;
            }
        }

        $this->info("Removed {$removed} stuck job(s) from '{$queue}' queue.");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} stuck job(s) that changed or disappeared during removal.");
        }
        $this->warn('Consider investigating why these jobs were stuck.');

        return 0;
    }
}
