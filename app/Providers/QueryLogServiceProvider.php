<?php

namespace App\Providers;

use App\Models\SystemLog;
use App\Services\QueryOptimizerService;
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
     * Cached request data for shutdown function
     */
    private ?array $requestData = null;

    /**
     * Whether shutdown logging is safe (app bootstrapped)
     */
    private bool $shutdownLoggingSafe = true;

    /**
     * Whether running in testing environment (cached at boot)
     */
    private bool $isTesting = false;

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('query.monitor', function ($app) {
            return new QueryOptimizerService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Always set defaults - even for early return
        $this->shutdownLoggingSafe = false;
        $this->isTesting = false;

        // Only monitor in non-production or when explicitly enabled
        if (! $this->shouldMonitor()) {
            return;
        }

        $this->slowQueryThreshold = config('database.slow_query_threshold_ms', 1000);
        $this->highQueryCountThreshold = config('database.high_query_count_threshold', 50);

        // Cache request data for shutdown function (request() not available then)
        $this->cacheRequestData();

        // Listen to all database queries
        DB::listen(function ($query) {
            $this->logQuery($query);
        });

        // Only register shutdown function if it's safe (not in testing/console without proper bootstrap)
        if ($this->shutdownLoggingSafe && function_exists('register_shutdown_function')) {
            register_shutdown_function([$this, 'logRequestSummary']);
        }
    }

    /**
     * Cache request data at boot time for use in shutdown function
     */
    private function cacheRequestData(): void
    {
        try {
            $this->requestData = [
                'url' => request()->url(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id(),
            ];
        } catch (\Exception $e) {
            // Request not available (e.g., in console/bootstrap context)
            $this->requestData = null;
        }
    }

    /**
     * Determine if query monitoring should be enabled
     */
    private function shouldMonitor(): bool
    {
        // Always disable shutdown logging by default until proven safe
        $this->shutdownLoggingSafe = false;
        $this->isTesting = false;

        // In testing environment, enable monitoring but disable shutdown logging
        if ($this->app->environment('testing')) {
            $this->isTesting = true;
            $this->shutdownLoggingSafe = false;

            return true;
        }

        // Check if running in console (CLI)
        if ($this->app->runningInConsole()) {
            $this->shutdownLoggingSafe = config('database.query_monitoring_console', false);

            return $this->shutdownLoggingSafe;
        }

        // Always monitor in debug mode
        if (config('app.debug')) {
            $this->shutdownLoggingSafe = true;

            return true;
        }

        // Check if explicitly enabled
        if (config('database.query_monitoring_enabled', false)) {
            $this->shutdownLoggingSafe = true;

            return true;
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

        $requestData = $this->requestData ?? [];

        // Log to file
        Log::channel('query')->warning($message, [
            'time_ms' => $queryData['time_ms'],
            'threshold_ms' => $this->slowQueryThreshold,
            'sql' => $queryData['sql'],
            'bindings' => $queryData['bindings'],
            'connection' => $queryData['connection'],
            'url' => $requestData['url'] ?? null,
            'method' => $requestData['method'] ?? null,
            'user_id' => $requestData['user_id'] ?? null,
        ]);

        // Log to database if very slow (> 5000ms)
        if ($queryData['time_ms'] > 5000 && $this->shouldLogToDatabase()) {
            try {
                SystemLog::create([
                    'user_id' => $requestData['user_id'] ?? null,
                    'action' => 'slow_query',
                    'entity_type' => 'database',
                    'entity_id' => null,
                    'details' => [
                        'sql' => substr($queryData['sql'], 0, 1000),
                        'time_ms' => $queryData['time_ms'],
                        'url' => $requestData['url'] ?? null,
                        'threshold_ms' => $this->slowQueryThreshold,
                    ],
                    'ip_address' => $requestData['ip'] ?? null,
                    'user_agent' => $requestData['user_agent'] ?? null,
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
        // Don't log during shutdown in testing - app container may be unavailable
        if ($this->isTesting) {
            return;
        }

        if (empty($this->requestQueries)) {
            return;
        }

        $requestData = $this->requestData ?? [];

        $totalQueries = count($this->requestQueries);
        $totalTime = array_sum(array_column($this->requestQueries, 'time_ms'));
        $slowQueries = array_filter($this->requestQueries, fn ($q) => $q['time_ms'] > $this->slowQueryThreshold);

        // Build summary
        $summary = [
            'url' => $requestData['url'] ?? null,
            'method' => $requestData['method'] ?? null,
            'total_queries' => $totalQueries,
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($totalTime / $totalQueries, 2),
            'slow_queries_count' => count($slowQueries),
            'user_id' => $requestData['user_id'] ?? null,
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
                    'user_id' => $requestData['user_id'] ?? null,
                    'action' => 'query_summary',
                    'entity_type' => 'request',
                    'entity_id' => null,
                    'details' => $summary,
                    'ip_address' => $requestData['ip'] ?? null,
                    'user_agent' => $requestData['user_agent'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log query summary to database: '.$e->getMessage());
            }
        }

        // Log warnings for performance issues
        if ($totalQueries > $this->highQueryCountThreshold) {
            Log::channel('query')->warning("High query count detected: {$totalQueries} queries", [
                'url' => $requestData['url'] ?? null,
                'suggestion' => 'Consider using eager loading or caching',
            ]);
        }

        if (count($slowQueries) > 5) {
            Log::channel('query')->warning('Multiple slow queries detected', [
                'count' => count($slowQueries),
                'url' => $requestData['url'] ?? null,
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
