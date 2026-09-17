<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\System\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        if (! config('security.rate_limits.enabled', true)) {
            return $next($request);
        }

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

        // Generate rate limit key — service includes user ID for authenticated requests
        $key = $this->rateLimitService->getRateLimitKey($request, $limiterName);

        // The burst allowance grants a small amount of extra capacity within the
        // SAME window (a token-bucket style credit). It must never let a client
        // exceed the window repeatedly: if bursts bypassed the window check, an
        // attacker could send burstAllowance requests every second forever.
        $effectiveLimit = $maxAttempts + $burstAllowance;

        // Enforce the window limit for ALL requests, including bursts.
        if (RateLimiter::tooManyAttempts($key, $effectiveLimit)) {
            $seconds = RateLimiter::availableIn($key);

            // Log the rate limit hit
            $this->rateLimitService->logRateLimitHit($request, $limiterName);

            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], 429)->withHeaders([
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
            ]);
        }

        // Per-second burst tracking (used to smooth out short spikes). This only
        // records timing data; it never bypasses the window cap above.
        if ($burstAllowance > 0) {
            try {
                $this->rateLimitService->checkBurst($request, $limiterName, $burstAllowance);
            } catch (\Throwable $e) {
                // Fail-open: a burst-tracking backend outage must not block legitimate traffic.
                Log::error('Rate limit burst check failed', [
                    'limiter' => $limiterName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Hit the rate limiter (burst requests count toward the window)
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
