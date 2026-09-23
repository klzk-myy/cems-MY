<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;
use Illuminate\Support\Facades\Log;

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
            // Kill-switch honoured only in local: a stale env value must never
            // silently remove rate limiting (including throttle:login and
            // throttle:sensitive) from a live deployment. Outside local the
            // switch is ignored with a loud alert — fail closed.
            if (app()->isLocal()) {
                return $next($request);
            }

            Log::alert('SECURITY_RATE_LIMITING_ENABLED=false outside local — ignoring kill-switch, rate limiting remains active', [
                'url' => $request->url(),
                'ip' => $request->ip(),
            ]);
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
