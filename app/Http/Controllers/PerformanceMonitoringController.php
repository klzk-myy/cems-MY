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
        $metrics = [
            'cache_stats' => $this->cacheMonitoringService->getCacheStats(),
            'query_count' => 0,
            'queries' => [],
        ];

        return view('performance.index', compact('metrics'));
    }
}
