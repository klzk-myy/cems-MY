<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Lightweight live health probes for the /performance dashboard.
 * Every probe is wrapped so a dead dependency reports 'down' instead of
 * breaking the page.
 */
class SystemHealthService
{
    /**
     * @return array{db_ping_ms: float|null, redis_ping_ms: float|null, queue_depth: int|null, failed_jobs: int|null}
     */
    public function probe(): array
    {
        return [
            'db_ping_ms' => $this->timed(fn () => DB::select('select 1')),
            'redis_ping_ms' => $this->timed(fn () => Redis::ping()),
            'queue_depth' => $this->guarded(fn () => Queue::size()),
            'failed_jobs' => $this->guarded(fn () => DB::table('failed_jobs')->count()),
        ];
    }

    private function timed(callable $probe): ?float
    {
        $start = microtime(true);

        $result = $this->guarded($probe);

        return $result === null ? null : round((microtime(true) - $start) * 1000, 2);
    }

    private function guarded(callable $probe): mixed
    {
        try {
            return $probe();
        } catch (\Throwable) {
            return null;
        }
    }
}
