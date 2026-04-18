<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureMfaEnabled Middleware
 *
 * Forces users to set up MFA on first login based on their role.
 * Users will be redirected to the MFA setup page until they enable MFA.
 */
class EnsureMfaEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // If MFA is not globally enabled, skip
        $mfaService = app(\App\Services\MfaService::class);
        if (! $mfaService->isGloballyEnabled()) {
            return $next($request);
        }

        // If MFA is required for this user's role but not yet enabled
        if ($mfaService->isMfaRequiredForRole($user) && ! $user->mfa_enabled) {
            // Check if MFA setup is already in progress
            if (! $request->routeIs('mfa.setup') && ! $request->routeIs('mfa.setup.store')) {
                return redirect()->route('mfa.setup');
            }
        }

        return $next($request);
    }
}
