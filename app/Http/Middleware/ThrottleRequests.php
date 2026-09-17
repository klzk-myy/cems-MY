<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;

/**
 * Rate limiting middleware honoring security.rate_limits.enabled.
 *
 * When SECURITY_RATE_LIMITING_ENABLED=false the request passes straight
 * through — both named limiters and inline throttle:N,M declarations.
 */
class ThrottleRequests extends BaseThrottleRequests
{
    /**
     * {@inheritDoc}
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (! config('security.rate_limits.enabled', true)) {
            return $next($request);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
