<?php

namespace App\Services\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryLoggingService
{
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

    public function getQuerySummary(): array
    {
        $queries = $this->getQueries();
        $totalTime = 0;

        foreach ($queries as $query) {
            $totalTime += $query['time'] ?? 0;
        }

        return [
            'count' => count($queries),
            'total_time_ms' => $totalTime,
            'slow_count' => $this->getSlowQueryCount(),
            'n_plus_one_count' => $this->getNPlusOneCount(),
        ];
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
