<?php

namespace App\Services\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QueryLoggingService
{
    protected const STATS_COUNT_KEY = 'perf:queries:count';

    protected const STATS_SLOW_KEY = 'perf:queries:slow_count';

    protected const STATS_NPLUSONE_KEY = 'perf:queries:n_plus_one';

    protected const STATS_TIME_KEY = 'perf:queries:total_time_ms';

    public function enable(): void
    {
        DB::enableQueryLog();
    }

    public function disable(): void
    {
        DB::disableQueryLog();
    }

    public function getQueries(): array
    {
        return DB::getQueryLog();
    }

    public function analyzeAndLog(Request $request): void
    {
        $queries = $this->getQueries();

        if (empty($queries)) {
            return;
        }

        $this->detectNPlusOne($queries, $request);
    }

    public function getQueryCount(): int
    {
        return count($this->getQueries());
    }

    public function getSlowQueryCount(float $thresholdMs = 100): int
    {
        $queries = $this->getQueries();
        $count = 0;

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;
            if ($time > $thresholdMs) {
                $count++;
            }
        }

        return $count;
    }

    public function getNPlusOneCount(): int
    {
        $queries = $this->getQueries();
        $queryCounts = [];

        foreach ($queries as $query) {
            $pattern = $this->normalizeQuery($query['query']);

            if (! isset($queryCounts[$pattern])) {
                $queryCounts[$pattern] = 0;
            }

            $queryCounts[$pattern]++;
        }

        $nPlusOneCount = 0;
        foreach ($queryCounts as $pattern => $count) {
            if ($count > 1) {
                $nPlusOneCount++;
            }
        }

        return $nPlusOneCount;
    }

    /**
     * Cumulative query counters for the /performance dashboard.
     *
     * Reads the Redis counters flushed by recordRequestStats() — not the
     * per-request DB query log (which is empty unless DB_LOGGING is on and
     * would only ever describe the current request anyway).
     */
    public function getQuerySummary(): array
    {
        try {
            return [
                'count' => (int) Redis::get(self::STATS_COUNT_KEY) ?: 0,
                'total_time_ms' => (float) Redis::get(self::STATS_TIME_KEY) ?: 0.0,
                'slow_count' => (int) Redis::get(self::STATS_SLOW_KEY) ?: 0,
                'n_plus_one_count' => (int) Redis::get(self::STATS_NPLUSONE_KEY) ?: 0,
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'total_time_ms' => 0.0, 'slow_count' => 0, 'n_plus_one_count' => 0];
        }
    }

    /**
     * Fold one request's executed queries into the cumulative Redis
     * counters. Accepts Illuminate\Database\Events\QueryExecuted objects.
     * Called once at app terminate so per-request overhead stays at a
     * single Redis round-trip per counter; all failures are swallowed —
     * instrumentation must never break a request.
     *
     * @param  iterable<int, object>  $queries
     */
    public function recordRequestStats(iterable $queries): void
    {
        $slowThreshold = (float) config('database.slow_query_threshold_ms', 1000);

        $count = 0;
        $totalTime = 0.0;
        $slow = 0;
        $patterns = [];

        foreach ($queries as $query) {
            $sql = (string) ($query->sql ?? '');
            $time = (float) ($query->time ?? 0);
            $bindings = is_array($query->bindings ?? null) ? $query->bindings : [];

            $count++;
            $totalTime += $time;
            if ($time > $slowThreshold) {
                $slow++;
            }

            $pattern = $this->normalizeQuery($sql);
            $id = $this->extractFirstIntegerBinding($sql, $bindings);
            $patterns[$pattern]['count'] = ($patterns[$pattern]['count'] ?? 0) + 1;
            if ($id !== null) {
                $patterns[$pattern]['ids'][$id] = true;
            }
        }

        if ($count === 0) {
            return;
        }

        $nPlusOne = 0;
        foreach ($patterns as $data) {
            if ($data['count'] >= 3 && count($data['ids'] ?? []) >= 2) {
                $nPlusOne++;
            }
        }

        try {
            Redis::incrby(self::STATS_COUNT_KEY, $count);
            Redis::incrbyfloat(self::STATS_TIME_KEY, $totalTime);
            if ($slow > 0) {
                Redis::incrby(self::STATS_SLOW_KEY, $slow);
            }
            if ($nPlusOne > 0) {
                Redis::incrby(self::STATS_NPLUSONE_KEY, $nPlusOne);
            }
        } catch (\Throwable) {
            // Instrumentation must never break the request.
        }
    }

    private function detectNPlusOne(array $queries, Request $request): void
    {
        $patterns = [];

        foreach ($queries as $query) {
            $pattern = $this->normalizeQuery($query['query']);
            $id = $this->extractFirstIntegerBinding($query['query'], $query['bindings'] ?? []);

            if (! isset($patterns[$pattern])) {
                $patterns[$pattern] = ['count' => 0, 'ids' => []];
            }

            $patterns[$pattern]['count']++;
            if ($id !== null) {
                $patterns[$pattern]['ids'][$id] = true;
            }
        }

        foreach ($patterns as $pattern => $data) {
            if ($data['count'] >= 3 && count($data['ids']) >= 2) {
                Log::warning('Potential N+1 query detected', [
                    'pattern' => $pattern,
                    'count' => $data['count'],
                    'unique_ids' => count($data['ids']),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            }
        }
    }

    private function extractFirstIntegerBinding(string $sql, array $bindings): ?int
    {
        foreach ($bindings as $binding) {
            if (is_int($binding)) {
                return $binding;
            }
            if (is_string($binding) && ctype_digit($binding)) {
                return (int) $binding;
            }
        }

        return null;
    }

    private function normalizeQuery(string $query): string
    {
        $query = preg_replace('/\s+/', ' ', $query);
        $query = preg_replace('/\d+/', '?', $query);
        $query = preg_replace("/'[^']*'/", '?', $query);

        return trim($query);
    }
}
