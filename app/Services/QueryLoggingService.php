<?php

namespace App\Services;

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

    private function detectNPlusOne(array $queries, Request $request): void
    {
        $queryCounts = [];

        foreach ($queries as $query) {
            $pattern = $this->normalizeQuery($query['query']);

            if (! isset($queryCounts[$pattern])) {
                $queryCounts[$pattern] = 0;
            }

            $queryCounts[$pattern]++;
        }

        foreach ($queryCounts as $pattern => $count) {
            if ($count > 1) {
                Log::warning('Potential N+1 query detected', [
                    'pattern' => $pattern,
                    'count' => $count,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
            }
        }
    }

    private function normalizeQuery(string $query): string
    {
        $query = preg_replace('/\s+/', ' ', $query);
        $query = preg_replace('/\d+/', '?', $query);
        $query = preg_replace('/\'[^\']*\'', '?', $query);

        return trim($query);
    }
}
