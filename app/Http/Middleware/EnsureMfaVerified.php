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
            // For API requests, return 401; for web, redirect to login
            if ($request->expectsJson()) {
                return $this->jsonResponse('Unauthenticated', 401);
            }

            return redirect()->route('login');
        }

        // If MFA is not globally enabled, skip
        if (! $this->mfaService->isGloballyEnabled()) {
            return $next($request);
        }

        // Consult the role requirement BEFORE looking at mfa_enabled: a user
        // in an MFA-required role cannot opt out by never enrolling.
        $mfaRequired = $this->mfaService->isMfaRequiredForRole($user);

        // Role requires MFA but the user never enrolled: honor the enrollment
        // grace period measured from account creation. After it lapses,
        // force enrollment instead of silently skipping MFA forever.
        if ($mfaRequired && ! $user->mfa_enabled) {
            if (! $this->mfaService->isEnrollmentOverdue($user)) {
                return $next($request);
            }

            if (! $request->expectsJson()) {
                return redirect()->route('mfa.setup');
            }

            // Point API clients at the API enrollment endpoint: an
            // un-enrolled user bounced to mfa.verify would be sent straight
            // back to setup.
            return $this->jsonResponse('MFA enrollment required', 403, '/api/v1/mfa/enroll');
        }

        // MFA optional for this role - skip verification entirely
        if (! $mfaRequired) {
            return $next($request);
        }

        // Check session lifetime first — even MFA cannot extend an expired session
        if ($this->sessionLifetimeExceeded($request)) {
            if (! $request->expectsJson()) {
                return redirect()->route('login');
            }

            return $this->jsonResponse('Session expired, please re-authenticate', 401);
        }

        // Check session MFA verification
        $verifiedAt = $this->sessionGet($request, 'mfa_verified_at');
        $maxAge = config('security.mfa_session_max_age', 900);

        if ($this->sessionGet($request, 'mfa_verified', false)
            && $verifiedAt
            && (now()->timestamp - $verifiedAt) <= $maxAge) {
            return $next($request);
        }

        // Trusted-device bypass: trust is bound to a random secret delivered
        // as a long-lived cookie; only the SHA-256 hash is stored server-side.
        // (User agent / IP are metadata on the record only.)
        $deviceToken = $request->cookie(MfaService::DEVICE_COOKIE_NAME);

        if (is_string($deviceToken) && $deviceToken !== ''
            && $this->mfaService->hasTrustedDevice($user, hash('sha256', $deviceToken))) {
            $this->sessionPut($request, 'mfa_verified', true);
            $this->sessionPut($request, 'mfa_verified_at', now()->timestamp);

            return $next($request);
        }

        // Web (non-JSON) requests should be redirected to the MFA verification page
        // instead of receiving a bare JSON 401 they cannot act on.
        if (! $request->expectsJson()) {
            return redirect()->route('mfa.verify');
        }

        return $this->jsonResponse('MFA verification required', 401);
    }

    /**
     * Whether the session has exceeded the absolute lifetime cap. Sessionless
     * requests (pure API tokens) have nothing to expire.
     */
    protected function sessionLifetimeExceeded(Request $request): bool
    {
        if (! $this->sessionExists($request)) {
            return false;
        }

        $sessionLifetime = config('security.session.lifetime', 480) * 60;
        $sessionCreatedAt = $this->sessionGet($request, '_session_created_at', now()->timestamp);

        return (now()->timestamp - $sessionCreatedAt) >= $sessionLifetime;
    }

    /**
     * Return a JSON response for API requests or redirect for web.
     */
    protected function jsonResponse(string $message, int $status, ?string $redirect = null): Response
    {
        $response = response()->json([
            'error' => $message,
            'redirect' => $redirect ?? '/api/v1/mfa/verify',
        ], $status);

        if ($status === 401) {
            $response->header('WWW-Authenticate', 'Bearer realm="mfa"');
        }

        return $response;
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
