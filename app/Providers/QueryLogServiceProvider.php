<?php

namespace App\Providers;

use App\Models\SystemLog;
use App\Services\System\QueryOptimizerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class QueryLogServiceProvider extends ServiceProvider
{
    private array $requestQueries = [];

    private float $slowQueryThreshold = 1000;

    private int $highQueryCountThreshold = 50;

    private ?array $requestData = null;

    private bool $monitoringEnabled = false;

    private bool $shutdownLoggingEnabled = false;

    private bool $isTestingEnvironment = false;

    public function register(): void
    {
        $this->app->singleton('query.monitor', function ($app) {
            return new QueryOptimizerService;
        });
    }

    public function boot(): void
    {
        $this->determineEnvironment();

        if (! $this->shouldMonitor()) {
            return;
        }

        $this->configureThresholds();
        $this->cacheRequestData();
        $this->registerQueryListener();
        $this->registerShutdownHandler();
    }

    private function determineEnvironment(): void
    {
        $this->isTestingEnvironment = $this->app->environment('testing');

        if ($this->isTestingEnvironment) {
            $this->monitoringEnabled = true;
            $this->shutdownLoggingEnabled = false;

            return;
        }

        if ($this->app->runningInConsole()) {
            $this->monitoringEnabled = config('database.query_monitoring_console', false);
            $this->shutdownLoggingEnabled = $this->monitoringEnabled;

            return;
        }

        $this->monitoringEnabled = config('app.debug') || config('database.query_monitoring_enabled', false);
        $this->shutdownLoggingEnabled = $this->monitoringEnabled;
    }

    private function shouldMonitor(): bool
    {
        return $this->monitoringEnabled;
    }

    private function shouldLogToDatabase(): bool
    {
        return ! $this->isTestingEnvironment;
    }

    private function configureThresholds(): void
    {
        $this->slowQueryThreshold = config('database.slow_query_threshold_ms', 1000);
        $this->highQueryCountThreshold = config('database.high_query_count_threshold', 50);
    }

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
            $this->requestData = null;
        }
    }

    private function registerQueryListener(): void
    {
        DB::listen(function ($query) {
            $this->logQuery($query);
        });
    }

    private function registerShutdownHandler(): void
    {
        if (! $this->shutdownLoggingEnabled || ! function_exists('register_shutdown_function')) {
            return;
        }

        register_shutdown_function([$this, 'logRequestSummary']);
    }

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

        if ($query->time > $this->slowQueryThreshold) {
            $this->logSlowQuery($queryData);
        }
    }

    private function logSlowQuery(array $queryData): void
    {
        $message = sprintf(
            'SLOW QUERY [%sms]: %s | Bindings: %s',
            $queryData['time_ms'],
            $queryData['sql'],
            json_encode($queryData['bindings'])
        );

        $requestData = $this->requestData ?? [];

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
                Log::error('Failed to log slow query to database: '.$e->getMessage());
            }
        }
    }

    public function logRequestSummary(): void
    {
        if ($this->isTestingEnvironment) {
            return;
        }

        if (empty($this->requestQueries)) {
            return;
        }

        $requestData = $this->requestData ?? [];

        $totalQueries = count($this->requestQueries);
        $totalTime = array_sum(array_column($this->requestQueries, 'time_ms'));
        $slowQueries = array_filter($this->requestQueries, fn ($q) => $q['time_ms'] > $this->slowQueryThreshold);

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

        Log::channel('query')->info('Request Query Summary', $summary);

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
