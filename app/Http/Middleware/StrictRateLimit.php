<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for strict rate limiting on sensitive endpoints.
 *
 * Implements BNM-compliant rate limits with burst protection
 * and proper logging for security monitoring.
 */
class StrictRateLimit
{
    public function __construct(
        private RateLimitService $rateLimitService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limiterName = 'default'): Response
    {
        // First check if IP is blocked
        if ($this->rateLimitService->isIpBlocked($request->ip())) {
            $blockInfo = $this->rateLimitService->getIpBlockInfo($request->ip());

            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your IP address has been temporarily blocked due to security policy violations.',
                'retry_after' => $blockInfo['expires_at'] ?? null,
            ], 403);
        }

        // Get rate limit configuration
        $config = config("security.rate_limits.{$limiterName}", config('security.rate_limits.api'));
        $maxAttempts = $config['attempts'] ?? 60;
        $decayMinutes = $config['decay_minutes'] ?? 1;
        $burstAllowance = $config['burst_allowance'] ?? 0;

        // Generate rate limit key
        $key = $this->rateLimitService->getRateLimitKey($request, $limiterName);

        // Check burst allowance first
        if ($burstAllowance > 0) {
            $withinBurst = $this->rateLimitService->checkBurst($request, $limiterName, $burstAllowance);
            if ($withinBurst) {
                return $next($request);
            }
        }

        // Check rate limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            // Log the rate limit hit
            $this->rateLimitService->logRateLimitHit($request, $limiterName);

            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], 429)->withHeaders([
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
            ]);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
