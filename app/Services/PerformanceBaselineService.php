<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PerformanceBaselineService
{
    protected const BASELINE_CACHE_KEY = 'performance_baseline';

    public function __construct(
        protected ThresholdService $thresholdService
    ) {}

    public function getBaseline(): array
    {
        return Cache::get(self::BASELINE_CACHE_KEY, [
            'response_time_ms' => 200,
            'cache_hit_rate' => 80.0,
            'queries_per_request' => 5,
            'memory_mb' => 128,
        ]);
    }

    public function setBaseline(array $baseline): void
    {
        Cache::forever(self::BASELINE_CACHE_KEY, $baseline);
    }

    public function updateBaselineMetric(string $key, mixed $value): void
    {
        $baseline = $this->getBaseline();
        $baseline[$key] = $value;
        $this->setBaseline($baseline);
    }

    public function compareResponseTime(float $currentMs): array
    {
        $baseline = $this->getBaseline();
        $threshold = (float) $this->thresholdService->getResponseTimeWarning();

        $variance = $baseline['response_time_ms'] > 0
            ? (($currentMs - $baseline['response_time_ms']) / $baseline['response_time_ms']) * 100
            : 0;

        return [
            'baseline' => $baseline['response_time_ms'],
            'current' => $currentMs,
            'variance_percent' => round($variance, 2),
            'exceeds_threshold' => $currentMs > $threshold,
            'status' => $variance > 20 ? 'degraded' : ($variance > 10 ? 'warning' : 'healthy'),
        ];
    }

    public function compareCacheHitRate(float $currentRate): array
    {
        $baseline = $this->getBaseline();
        $threshold = (float) $this->thresholdService->getCacheHitRateWarning();

        $variance = $baseline['cache_hit_rate'] > 0
            ? (($baseline['cache_hit_rate'] - $currentRate) / $baseline['cache_hit_rate']) * 100
            : 0;

        return [
            'baseline' => $baseline['cache_hit_rate'],
            'current' => $currentRate,
            'variance_percent' => round($variance, 2),
            'below_threshold' => $currentRate < $threshold,
            'status' => $variance > 20 ? 'degraded' : ($variance > 10 ? 'warning' : 'healthy'),
        ];
    }

    public function compareQueriesPerRequest(int $currentCount): array
    {
        $baseline = $this->getBaseline();

        $variance = $baseline['queries_per_request'] > 0
            ? (($currentCount - $baseline['queries_per_request']) / $baseline['queries_per_request']) * 100
            : 0;

        return [
            'baseline' => $baseline['queries_per_request'],
            'current' => $currentCount,
            'variance_percent' => round($variance, 2),
            'exceeds_baseline' => $currentCount > $baseline['queries_per_request'],
            'status' => $variance > 20 ? 'degraded' : ($variance > 10 ? 'warning' : 'healthy'),
        ];
    }

    public function isPerformanceHealthy(): bool
    {
        $currentResponseTime = $this->getCurrentResponseTime();
        $currentCacheHitRate = $this->getCurrentCacheHitRate();

        $responseTimeCheck = $this->compareResponseTime($currentResponseTime);
        $cacheHitRateCheck = $this->compareCacheHitRate($currentCacheHitRate);

        return $responseTimeCheck['status'] === 'healthy' && $cacheHitRateCheck['status'] === 'healthy';
    }

    protected function getCurrentResponseTime(): float
    {
        return (float) Cache::get('current_response_time_ms', 0);
    }

    protected function getCurrentCacheHitRate(): float
    {
        return (float) Cache::get('current_cache_hit_rate', 0);
    }
}
