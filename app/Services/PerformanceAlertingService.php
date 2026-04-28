<?php

namespace App\Services;

class PerformanceAlertingService
{
    public function __construct(
        protected SystemAlertService $alertService,
        protected ThresholdService $thresholdService
    ) {}

    public function checkResponseTime(string $endpoint, float $durationMs): bool
    {
        $threshold = (float) $this->thresholdService->getResponseTimeWarning();

        if ($durationMs > $threshold) {
            $this->alertService->warning(
                'Slow response time detected',
                [
                    'source' => 'performance',
                    'metadata' => [
                        'endpoint' => $endpoint,
                        'duration_ms' => $durationMs,
                        'threshold_ms' => $threshold,
                    ],
                ]
            );

            return true;
        }

        return false;
    }

    public function checkCacheHitRate(float $hitRate): bool
    {
        $threshold = (float) $this->thresholdService->getCacheHitRateWarning();

        if ($hitRate < $threshold) {
            $this->alertService->warning(
                'Low cache hit rate',
                [
                    'source' => 'performance',
                    'metadata' => [
                        'hit_rate' => $hitRate,
                        'threshold' => $threshold,
                    ],
                ]
            );

            return true;
        }

        return false;
    }

    public function checkQueryTime(float $queryTimeMs): bool
    {
        $threshold = (float) $this->thresholdService->getQueryTimeWarning();

        if ($queryTimeMs > $threshold) {
            $this->alertService->warning(
                'Slow query detected',
                [
                    'source' => 'performance',
                    'metadata' => [
                        'query_time_ms' => $queryTimeMs,
                        'threshold_ms' => $threshold,
                    ],
                ]
            );

            return true;
        }

        return false;
    }
}
