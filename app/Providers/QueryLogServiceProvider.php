<?php

namespace App\Providers;

use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Query Log Service Provider
 *
 * Monitors database queries for performance:
 * - Logs queries taking > 1000ms
 * - Tracks query count per request
 * - Logs to both file and database
 */
class QueryLogServiceProvider extends ServiceProvider
{
    /**
     * Query log for current request
     */
    private array $requestQueries = [];

    /**
     * Slow query threshold in milliseconds
     */
    private float $slowQueryThreshold = 1000;

    /**
     * High query count threshold
     */
    private int $highQueryCountThreshold = 50;

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('query.monitor', function ($app) {
            return new \App\Services\QueryOptimizerService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only monitor in non-production or when explicitly enabled
        if (! $this->shouldMonitor()) {
            return;
        }

        $this->slowQueryThreshold = config('database.slow_query_threshold', 1000);
        $this->highQueryCountThreshold = config('database.high_query_count_threshold', 50);

        // Listen to all database queries
        DB::listen(function ($query) {
            $this->logQuery($query);
        });

        // Log summary at end of request
        if (function_exists('register_shutdown_function')) {
            register_shutdown_function([$this, 'logRequestSummary']);
        }
    }

    /**
     * Determine if query monitoring should be enabled
     */
    private function shouldMonitor(): bool
    {
        // Always monitor in debug mode
        if (config('app.debug')) {
            return true;
        }

        // Check if explicitly enabled
        if (config('database.query_monitoring_enabled', false)) {
            return true;
        }

        // Check if running in console (CLI)
        if ($this->app->runningInConsole()) {
            return config('database.query_monitoring_console', false);
        }

        return false;
    }

    /**
     * Log individual query
     */
    private function logQuery($query): void
    {
        $queryData = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time_ms' => $query->time,
            'connection' => $query->connectionName,
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->requestQueries[] = $queryData;

        // Log slow queries immediately
        if ($query->time > $this->slowQueryThreshold) {
            $this->logSlowQuery($queryData);
        }
    }

    /**
     * Log slow query to file and database
     */
    private function logSlowQuery(array $queryData): void
    {
        $message = sprintf(
            'SLOW QUERY [%sms]: %s | Bindings: %s',
            $queryData['time_ms'],
            $queryData['sql'],
            json_encode($queryData['bindings'])
        );

        // Log to file
        Log::channel('query')->warning($message, [
            'time_ms' => $queryData['time_ms'],
            'threshold_ms' => $this->slowQueryThreshold,
            'sql' => $queryData['sql'],
            'bindings' => $queryData['bindings'],
            'connection' => $queryData['connection'],
            'url' => request()->url(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
        ]);

        // Log to database if very slow (> 5000ms)
        if ($queryData['time_ms'] > 5000 && $this->shouldLogToDatabase()) {
            try {
                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'slow_query',
                    'entity_type' => 'database',
                    'entity_id' => null,
                    'details' => [
                        'sql' => substr($queryData['sql'], 0, 1000),
                        'time_ms' => $queryData['time_ms'],
                        'url' => request()->url(),
                        'threshold_ms' => $this->slowQueryThreshold,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } catch (\Exception $e) {
                // Don't let logging errors affect the application
                Log::error('Failed to log slow query to database: '.$e->getMessage());
            }
        }
    }

    /**
     * Log request summary at end of request
     */
    public function logRequestSummary(): void
    {
        if (empty($this->requestQueries)) {
            return;
        }

        $totalQueries = count($this->requestQueries);
        $totalTime = array_sum(array_column($this->requestQueries, 'time_ms'));
        $slowQueries = array_filter($this->requestQueries, fn ($q) => $q['time_ms'] > $this->slowQueryThreshold);

        // Build summary
        $summary = [
            'url' => request()->url(),
            'method' => request()->method(),
            'total_queries' => $totalQueries,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($totalTime / $totalQueries, 2),
            'slow_queries_count' => count($slowQueries),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ];

        // Log to file
        Log::channel('query')->info('Request Query Summary', $summary);

        // Log to database if high query count or slow queries detected
        if (($totalQueries > $this->highQueryCountThreshold || count($slowQueries) > 0)
            && $this->shouldLogToDatabase()
        ) {
            try {
                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'query_summary',
                    'entity_type' => 'request',
                    'entity_id' => null,
                    'details' => $summary,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log query summary to database: '.$e->getMessage());
            }
        }

        // Log warnings for performance issues
        if ($totalQueries > $this->highQueryCountThreshold) {
            Log::channel('query')->warning("High query count detected: {$totalQueries} queries", [
                'url' => request()->url(),
                'suggestion' => 'Consider using eager loading or caching',
            ]);
        }

        if (count($slowQueries) > 5) {
            Log::channel('query')->warning('Multiple slow queries detected', [
                'count' => count($slowQueries),
                'url' => request()->url(),
                'suggestion' => 'Review database indexes and query optimization',
            ]);
        }
    }

    /**
     * Check if should log to database
     */
    private function shouldLogToDatabase(): bool
    {
        // Don't log in test environment to avoid cluttering logs
        if (app()->environment('testing')) {
            return false;
        }

        return true;
    }

    /**
     * Get current request statistics
     */
    public function getRequestStats(): array
    {
        return [
            'query_count' => count($this->requestQueries),
            'total_time_ms' => array_sum(array_column($this->requestQueries, 'time_ms')),
            'slow_queries' => count(array_filter(
                $this->requestQueries,
                fn ($q) => $q['time_ms'] > $this->slowQueryThreshold
            )),
        ];
    }
}
