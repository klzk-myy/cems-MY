<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for IP-based blocking.
 *
 * Checks if the requesting IP is blocked before allowing access.
 * Also tracks failed authentication attempts for auto-blocking.
 */
class IpBlocker
{
    public function __construct(
        private RateLimitService $rateLimitService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check if IP is blocked
        if ($this->rateLimitService->isIpBlocked($ip)) {
            $blockInfo = $this->rateLimitService->getIpBlockInfo($ip);

            // Log blocked attempt
            Log::warning('Blocked IP attempted access', [
                'ip' => $ip,
                'url' => $request->url(),
                'user_agent' => $request->userAgent(),
                'blocked_since' => $blockInfo['blocked_at'] ?? null,
            ]);

            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your IP address has been temporarily blocked due to security policy violations.',
                'code' => 'IP_BLOCKED',
                'retry_after' => $blockInfo['expires_at'] ?? null,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Record a failed authentication attempt.
     * This should be called from authentication controllers after failed login.
     */
    public function recordFailedAuth(Request $request): void
    {
        $ip = $request->ip();
        $this->rateLimitService->recordFailedAttempt($ip);
    }

    /**
     * Clear failed attempts for successful authentication.
     * This should be called from authentication controllers after successful login.
     */
    public function clearFailedAuth(Request $request): void
    {
        $ip = $request->ip();
        $this->rateLimitService->clearFailedAttempts($ip);
    }
}
