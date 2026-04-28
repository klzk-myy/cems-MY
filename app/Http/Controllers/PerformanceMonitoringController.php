<?php

namespace App\Http\Controllers;

use App\Services\CacheMonitoringService;
use App\Services\QueryLoggingService;

class PerformanceMonitoringController extends Controller
{
    public function __construct(
        protected CacheMonitoringService $cacheMonitoringService,
        protected QueryLoggingService $queryLoggingService
    ) {}

    public function index()
    {
        $querySummary = $this->queryLoggingService->getQuerySummary();

        $metrics = [
            'cache_stats' => $this->cacheMonitoringService->getCacheStats(),
            'query_count' => $querySummary['count'],
            'slow_query_count' => $querySummary['slow_count'],
            'n_plus_one_count' => $querySummary['n_plus_one_count'],
            'total_query_time_ms' => $querySummary['total_time_ms'],
        ];

        return view('performance.index', compact('metrics'));
    }
}
