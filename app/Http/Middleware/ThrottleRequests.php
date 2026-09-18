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

        // Named-limiter detection must happen here: parent::handle() checks
        // func_num_args() === 3, but it always receives five arguments from
        // this call, so the upstream named-limiter branch would never run
        // and 'throttle:api' would crash in resolveMaxAttempts().
        if (is_string($maxAttempts)
            && func_num_args() === 3
            && ! is_null($limiter = $this->limiter->limiter($maxAttempts))) {
            return $this->handleRequestUsingNamedLimiter($request, $next, $maxAttempts, $limiter);
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
