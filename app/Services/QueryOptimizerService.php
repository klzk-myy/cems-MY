<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query Optimizer Service
 *
 * Detects N+1 queries, suggests eager loading optimizations,
 * and logs slow queries for performance monitoring.
 */
class QueryOptimizerService
{
    /**
     * Query log for analysis
     */
    private array $queryLog = [];

    /**
     * Detected N+1 query patterns
     */
    private array $nPlusOnePatterns = [];

    /**
     * Slow query threshold in milliseconds
     */
    private float $slowQueryThreshold;

    public function __construct()
    {
        $this->slowQueryThreshold = config('database.slow_query_threshold_ms', 1000);
    }

    /**
     * Start monitoring queries for the current request
     */
    public function startMonitoring(): void
    {
        $this->queryLog = [];
        $this->nPlusOnePatterns = [];

        DB::listen(function ($query) {
            $this->queryLog[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            ];

            // Log slow queries immediately
            if ($query->time > $this->slowQueryThreshold) {
                $this->logSlowQuery($query);
            }
        });
    }

    /**
     * Analyze queries and detect N+1 patterns
     */
    public function analyzeQueries(): array
    {
        $this->detectNPlusOneQueries();
        $this->detectMissingIndexes();
        $this->detectRepeatedQueries();

        return [
            'n_plus_one' => $this->nPlusOnePatterns,
            'total_queries' => count($this->queryLog),
            'slow_queries' => $this->getSlowQueries(),
            'suggestions' => $this->generateSuggestions(),
        ];
    }

    /**
     * Detect N+1 query patterns
     */
    private function detectNPlusOneQueries(): void
    {
        $patterns = [];

        // Group queries by their base SQL pattern (without IDs)
        foreach ($this->queryLog as $query) {
            $pattern = $this->extractQueryPattern($query['sql']);

            if (! isset($patterns[$pattern])) {
                $patterns[$pattern] = [];
            }
            $patterns[$pattern][] = $query;
        }

        // Identify patterns that repeat with different IDs
        foreach ($patterns as $pattern => $queries) {
            if (count($queries) >= 3) {
                // Check if queries are similar but with different where clauses
                $ids = array_map(fn ($q) => $this->extractIdFromQuery($q['sql']), $queries);
                $uniqueIds = array_unique($ids);

                if (count($uniqueIds) > 2) {
                    $this->nPlusOnePatterns[] = [
                        'pattern' => $pattern,
                        'count' => count($queries),
                        'suggestion' => $this->suggestEagerLoading($pattern, $queries),
                        'sample_queries' => array_slice($queries, 0, 3),
                    ];
                }
            }
        }
    }

    /**
     * Detect queries that might benefit from indexes
     */
    private function detectMissingIndexes(): void
    {
        $slowQueries = array_filter($this->queryLog, fn ($q) => $q['time'] > 500);

        foreach ($slowQueries as $query) {
            // Check for common patterns that need indexes
            if (str_contains($query['sql'], 'where') && ! str_contains($query['sql'], 'like')) {
                $columns = $this->extractWhereColumns($query['sql']);

                foreach ($columns as $column) {
                    if ($this->isForeignKeyColumn($column) && $query['time'] > 1000) {
                        Log::warning('Potential missing index detected', [
                            'column' => $column,
                            'sql' => $query['sql'],
                            'time_ms' => $query['time'],
                            'suggestion' => "Consider adding index on column: {$column}",
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Detect repeated identical queries
     */
    private function detectRepeatedQueries(): void
    {
        $queryHashes = [];

        foreach ($this->queryLog as $query) {
            $hash = md5($query['sql'].serialize($query['bindings']));

            if (! isset($queryHashes[$hash])) {
                $queryHashes[$hash] = ['count' => 0, 'query' => $query];
            }
            $queryHashes[$hash]['count']++;
        }

        // Log queries executed more than 3 times
        foreach ($queryHashes as $hash => $data) {
            if ($data['count'] > 3) {
                Log::info('Repeated query detected', [
                    'count' => $data['count'],
                    'sql' => substr($data['query']['sql'], 0, 200),
                    'suggestion' => 'Consider caching this query result',
                ]);
            }
        }
    }

    /**
     * Suggest eager loading for a query pattern
     */
    private function suggestEagerLoading(string $pattern, array $queries): string
    {
        // Extract table name from pattern
        preg_match('/from\s+`?(\w+)`?/i', $pattern, $matches);
        $table = $matches[1] ?? 'unknown';

        // Determine relationship based on query pattern
        $suggestions = [
            'customers' => 'Consider using with([\'transactions\', \'documents\', \'riskHistory\'])',
            'transactions' => 'Consider using with([\'customer\', \'user\', \'branch\', \'currency\'])',
            'counters' => 'Consider using with([\'branch\', \'sessions\'])',
            'branches' => 'Consider using with([\'counters\', \'users\'])',
            'currencies' => 'Consider using with([\'positions\'])',
            'system_logs' => 'Consider using with([\'user\'])',
            'flagged_transactions' => 'Consider using with([\'transaction\', \'customer\'])',
            'compliance_cases' => 'Consider using with([\'customer\', \'assignedTo\', \'notes\', \'documents\'])',
            'journal_entries' => 'Consider using with([\'lines\', \'lines.account\'])',
            'account_ledger' => 'Consider using with([\'account\'])',
            'till_balances' => 'Consider using with([\'counter\', \'user\'])',
            'str_reports' => 'Consider using with([\'transactions\', \'customer\', \'createdBy\'])',
            'stock_transfers' => 'Consider using with([\'fromBranch\', \'toBranch\', \'items\'])',
        ];

        return $suggestions[$table] ?? "Consider eager loading related models for {$table}";
    }

    /**
     * Extract query pattern (normalize SQL)
     */
    private function extractQueryPattern(string $sql): string
    {
        // Replace specific values with placeholders
        $pattern = preg_replace('/=\s*\d+/', '= ?', $sql);
        $pattern = preg_replace('/in\s*\([^)]+\)/i', 'in (?)', $pattern);

        return $pattern;
    }

    /**
     * Extract ID from WHERE clause
     */
    private function extractIdFromQuery(string $sql): ?int
    {
        if (preg_match('/where\s+\w+\.id\s*=\s*(\d+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/where\s+id\s*=\s*(\d+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract WHERE clause columns
     */
    private function extractWhereColumns(string $sql): array
    {
        $columns = [];

        if (preg_match_all('/where\s+(\w+)\./i', $sql, $matches)) {
            $columns = array_merge($columns, $matches[1]);
        }

        if (preg_match_all('/and\s+(\w+)\./i', $sql, $matches)) {
            $columns = array_merge($columns, $matches[1]);
        }

        return array_unique($columns);
    }

    /**
     * Check if column is likely a foreign key
     */
    private function isForeignKeyColumn(string $column): bool
    {
        return str_ends_with($column, '_id');
    }

    /**
     * Get slow queries from log
     */
    private function getSlowQueries(): array
    {
        return array_filter($this->queryLog, fn ($q) => $q['time'] > $this->slowQueryThreshold);
    }

    /**
     * Log slow query
     */
    private function logSlowQuery($query): void
    {
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time_ms' => $query->time,
            'threshold_ms' => $this->slowQueryThreshold,
            'bindings' => $query->bindings,
        ]);
    }

    /**
     * Generate optimization suggestions
     */
    private function generateSuggestions(): array
    {
        $suggestions = [];

        foreach ($this->nPlusOnePatterns as $pattern) {
            $suggestions[] = [
                'type' => 'n_plus_one',
                'priority' => 'high',
                'message' => "N+1 query detected: {$pattern['pattern']}",
                'suggestion' => $pattern['suggestion'],
                'impact' => "{$pattern['count']} queries executed",
            ];
        }

        if (count($this->queryLog) > 50) {
            $suggestions[] = [
                'type' => 'query_count',
                'priority' => 'medium',
                'message' => 'High query count detected',
                'suggestion' => 'Consider using eager loading or caching to reduce query count',
                'impact' => count($this->queryLog).' queries executed',
            ];
        }

        return $suggestions;
    }

    /**
     * Check if a model has potential N+1 issues
     */
    public function checkModelRelations(Model $model): array
    {
        $issues = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods() as $method) {
            if ($method->class === get_class($model) && $method->isPublic()) {
                $returnType = $method->getReturnType();

                if ($returnType && str_contains($returnType->getName(), 'Illuminate\Database\Eloquent\Relations')) {
                    // This is a relation method
                    $relationName = $method->getName();

                    // Check if it was loaded
                    if (! $model->relationLoaded($relationName)) {
                        $issues[] = [
                            'relation' => $relationName,
                            'type' => $returnType->getName(),
                            'suggestion' => "Use \$model->load('{$relationName}') or with('{$relationName}')",
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Get query statistics
     */
    public function getQueryStatistics(): array
    {
        $totalTime = array_sum(array_column($this->queryLog, 'time'));
        $avgTime = count($this->queryLog) > 0 ? $totalTime / count($this->queryLog) : 0;
        $slowCount = count($this->getSlowQueries());

        return [
            'total_queries' => count($this->queryLog),
            'total_time_ms' => round($totalTime, 2),
            'avg_time_ms' => round($avgTime, 2),
            'slow_queries' => $slowCount,
            'n_plus_one_issues' => count($this->nPlusOnePatterns),
        ];
    }

    /**
     * Clear query log
     */
    public function clearLog(): void
    {
        $this->queryLog = [];
        $this->nPlusOnePatterns = [];
    }
}
