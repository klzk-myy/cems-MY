<?php

namespace App\Http\Middleware;

use App\Services\System\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureMfaEnabled Middleware
 *
 * Forces users in MFA-required roles to enroll once the enrollment grace
 * period (from account creation) has lapsed. Unlike EnsureMfaVerified —
 * which only guards routes tagged `mfa.verified` — this runs on every
 * authenticated web request so a user who never touches a sensitive route
 * cannot defer enrollment indefinitely.
 */
class EnsureMfaEnabled
{
    public function __construct(
        protected MfaService $mfaService
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user
            || ! $this->mfaService->isGloballyEnabled()
            || ! $this->mfaService->isEnrollmentOverdue($user)) {
            return $next($request);
        }

        // Paths that must stay reachable while enrollment is forced: the MFA
        // flow itself, logout, forced password rotation, and the notification
        // bell polling endpoint (a redirect there only creates noise).
        if ($request->routeIs('mfa.*', 'logout', 'password.*', 'notifications.*')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'MFA enrollment required',
                'redirect' => '/api/v1/mfa/enroll',
            ], 403);
        }

        return redirect()->route('mfa.setup')
            ->with('warning', 'Multi-factor authentication enrollment is required for your role.');
    }
}
