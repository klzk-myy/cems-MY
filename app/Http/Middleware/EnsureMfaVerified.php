<?php

namespace App\Http\Middleware;

use App\Services\System\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureMfaVerified Middleware
 *
 * Requires MFA verification before accessing sensitive operations.
 * Users must complete MFA verification via session or trusted device.
 */
class EnsureMfaVerified
{
    public function __construct(
        protected MfaService $mfaService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // If MFA is not globally enabled, skip
        if (! $this->mfaService->isGloballyEnabled()) {
            return $next($request);
        }

        // If MFA not enabled for user, skip
        if (! $user->mfa_enabled) {
            return $next($request);
        }

        // Check if MFA is required for this user's role
        if (! $this->mfaService->isMfaRequiredForRole($user)) {
            return $next($request);
        }

        // Check session lifetime first — even MFA cannot extend an expired session
        if ($this->sessionExists($request)) {
            $sessionLifetime = config('security.session.lifetime', 480) * 60;
            $sessionCreatedAt = $this->sessionGet($request, '_session_created_at', now()->timestamp);
            $sessionElapsed = now()->timestamp - $sessionCreatedAt;

            if ($sessionElapsed >= $sessionLifetime) {
                return $this->jsonResponse('Session expired, please re-authenticate', 401);
            }
        }

        // Check session MFA verification
        $verifiedAt = $this->sessionGet($request, 'mfa_verified_at');
        $maxAge = config('security.mfa_session_max_age', 900);

        if ($this->sessionGet($request, 'mfa_verified', false)
            && $verifiedAt
            && (now()->timestamp - $verifiedAt) <= $maxAge) {
            return $next($request);
        }

        // Check trusted device bypass
        $fingerprint = $this->mfaService->generateDeviceFingerprint();
        if ($this->mfaService->hasTrustedDevice($user, $fingerprint)) {
            $this->sessionPut($request, 'mfa_verified', true);
            $this->sessionPut($request, 'mfa_verified_at', now()->timestamp);

            return $next($request);
        }

        return $this->jsonResponse('MFA verification required', 403);
    }

    /**
     * Return a JSON response for API requests or redirect for web.
     */
    protected function jsonResponse(string $message, int $status): Response
    {
        return response()->json([
            'error' => $message,
            'redirect' => route('mfa.verify'),
        ], $status);
    }

    /**
     * Check if the request has a usable session store.
     */
    protected function sessionExists(Request $request): bool
    {
        try {
            $request->session()->all();

            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Safely get a value from session, returning default if session unavailable.
     */
    protected function sessionGet(Request $request, string $key, mixed $default = null): mixed
    {
        try {
            return $request->session()->get($key, $default);
        } catch (\RuntimeException $e) {
            return $default;
        }
    }

    /**
     * Safely put a value into session.
     */
    protected function sessionPut(Request $request, string $key, mixed $value): void
    {
        try {
            $request->session()->put($key, $value);
        } catch (\RuntimeException $e) {
            // Session not available
        }
    }
}
