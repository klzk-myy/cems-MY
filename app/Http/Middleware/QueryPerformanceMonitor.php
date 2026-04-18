<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query Performance Monitor Middleware
 *
 * Monitors database query performance and logs slow queries.
 * Tracks query counts per request to detect N+1 query issues.
 *
 * Configuration:
 * - QUERY_SLOW_THRESHOLD_MS: Threshold for slow query warning (default: 1000ms)
 * - QUERY_COUNT_THRESHOLD: Threshold for excessive query warning (default: 50)
 */
class QueryPerformanceMonitor
{
    /**
     * Slow query threshold in milliseconds
     */
    protected int $slowQueryThreshold;

    /**
     * Maximum acceptable queries per request
     */
    protected int $queryCountThreshold;

    /**
     * Whether monitoring is enabled
     */
    protected bool $enabled;

    public function __construct()
    {
        $this->slowQueryThreshold = config('database.slow_query_threshold_ms', 1000);
        $this->queryCountThreshold = config('database.query_count_threshold', 50);
        $this->enabled = config('database.query_monitoring_enabled', app()->environment('production'));
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->enabled) {
            return $next($request);
        }

        // Collect query statistics
        $queries = [];
        $totalTime = 0;

        DB::listen(function ($query) use (&$queries, &$totalTime) {
            $timeMs = $query->time;
            $totalTime += $timeMs;

            $queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $timeMs,
                'slow' => $timeMs > $this->slowQueryThreshold,
            ];
        });

        $startTime = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $startTime) * 1000;

        // Remove listener to prevent capturing subsequent queries
        DB::forget('listen');

        // Analyze and log performance
        $this->analyzePerformance($request, $queries, $totalTime, $duration);

        return $response;
    }

    /**
     * Analyze query performance and log warnings
     */
    protected function analyzePerformance($request, array $queries, float $totalQueryTime, float $totalDuration): void
    {
        $queryCount = count($queries);
        $slowQueries = array_filter($queries, fn ($q) => $q['slow']);
        $slowQueryCount = count($slowQueries);

        // Always log in debug mode, only log issues in production
        $shouldLog = app()->environment('local', 'debug') || $slowQueryCount > 0 || $queryCount > $this->queryCountThreshold;

        if (! $shouldLog) {
            return;
        }

        $context = [
            'url' => $request->url(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'query_count' => $queryCount,
            'slow_query_count' => $slowQueryCount,
            'total_query_time_ms' => round($totalQueryTime, 2),
            'total_duration_ms' => round($totalDuration, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        // Log slow queries
        if ($slowQueryCount > 0) {
            $slowQueryDetails = array_slice($slowQueries, 0, 5); // Log top 5 slow queries
            foreach ($slowQueryDetails as $query) {
                Log::warning('Slow query detected', array_merge($context, [
                    'query_time_ms' => round($query['time'], 2),
                    'sql' => $this->sanitizeSql($query['sql']),
                ]));
            }
        }

        // Log excessive query count
        if ($queryCount > $this->queryCountThreshold) {
            Log::warning('Excessive query count detected - possible N+1 issue', array_merge($context, [
                'threshold' => $this->queryCountThreshold,
            ]));

            // Identify potential duplicate queries
            $duplicatePatterns = $this->findDuplicateQueryPatterns($queries);
            if (! empty($duplicatePatterns)) {
                Log::warning('Potential N+1 query pattern detected', array_merge($context, [
                    'duplicate_patterns' => $duplicatePatterns,
                ]));
            }
        }

        // Log summary in debug mode
        if (app()->environment('local', 'debug')) {
            Log::debug('Query performance summary', $context);
        }
    }

    /**
     * Find duplicate query patterns that might indicate N+1 issues
     */
    protected function findDuplicateQueryPatterns(array $queries): array
    {
        $patterns = [];

        foreach ($queries as $query) {
            // Normalize SQL to identify similar patterns
            $pattern = preg_replace('/\d+/', '?', $query['sql']);
            $pattern = preg_replace('/in \([^)]+\)/i', 'in (?)', $pattern);

            if (! isset($patterns[$pattern])) {
                $patterns[$pattern] = 0;
            }
            $patterns[$pattern]++;
        }

        // Find patterns executed multiple times
        $duplicates = [];
        foreach ($patterns as $pattern => $count) {
            if ($count > 3) { // More than 3 similar queries
                $duplicates[] = [
                    'pattern' => substr($pattern, 0, 100).(strlen($pattern) > 100 ? '...' : ''),
                    'count' => $count,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Sanitize SQL for logging (remove sensitive data)
     */
    protected function sanitizeSql(string $sql): string
    {
        // Truncate very long queries
        if (strlen($sql) > 500) {
            $sql = substr($sql, 0, 500).'...';
        }

        return $sql;
    }
}
