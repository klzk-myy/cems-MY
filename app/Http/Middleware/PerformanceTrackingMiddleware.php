<?php

namespace App\Http\Middleware;

use App\Services\ThresholdService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceTrackingMiddleware
{
    public function __construct(
        protected ThresholdService $thresholdService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;

        Log::info('Request performance', [
            'url' => $request->url(),
            'method' => $request->method(),
            'duration_ms' => round($duration, 2),
            'status' => $response->status(),
        ]);

        $threshold = (float) $this->thresholdService->getResponseTimeWarning();
        if ($duration > $threshold) {
            Log::warning('Slow endpoint detected', [
                'url' => $request->url(),
                'method' => $request->method(),
                'duration_ms' => round($duration, 2),
                'threshold_ms' => $threshold,
            ]);
        }

        return $response;
    }
}
