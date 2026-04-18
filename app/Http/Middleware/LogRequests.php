<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Handle an incoming request and log details with timing.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);

        // Log request start
        Log::info('Request started', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // Duration in milliseconds
        $statusCode = $response->getStatusCode();

        // Determine log level based on status code
        $logLevel = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');

        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add memory usage for debugging
        if ($duration > 1000 || $statusCode >= 500) {
            $logData['memory_usage_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        }

        Log::$logLevel('Request completed', $logData);

        // Add request ID to response header for traceability
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
