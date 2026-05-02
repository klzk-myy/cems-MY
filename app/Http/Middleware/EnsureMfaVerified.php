<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureMfaVerified Middleware
 *
 * Requires MFA verification before accessing sensitive operations.
 * Users must complete MFA verification in the current session.
 */
class EnsureMfaVerified
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
        $mfaService = app(MfaService::class);
        if (! $mfaService->isGloballyEnabled()) {
            return $next($request);
        }

        // If MFA not enabled for user, skip
        if (! $user->mfa_enabled) {
            return $next($request);
        }

        // Check if MFA is required for this user's role
        if (! $mfaService->isMfaRequiredForRole($user)) {
            return $next($request);
        }

        // Check if already verified in this session with expiration
        if ($request->session()->get('mfa_verified', false)) {
            $verifiedAt = $request->session()->get('mfa_verified_at');
            $maxAge = config('security.mfa_session_max_age', 900); // 15 minutes default

            // Check if verification has expired
            if ($verifiedAt && now()->timestamp - $verifiedAt <= $maxAge) {
                return $next($request);
            }

            // Verification has expired, clear the session
            $request->session()->forget(['mfa_verified', 'mfa_verified_at']);
        }

        // Check for trusted device (with time-limited verification)
        $fingerprint = $mfaService->generateDeviceFingerprint();
        if ($mfaService->hasTrustedDevice($user, $fingerprint)) {
            // Trusted device verification is also time-limited to prevent indefinite extension
            $maxAge = config('security.mfa_session_max_age', 900); // 15 minutes default

            // Mark session as verified with timestamp
            $request->session()->put('mfa_verified', true);
            $request->session()->put('mfa_verified_at', now()->timestamp);
            // Store trusted device verification for expiry tracking
            $request->session()->put('mfa_trusted_device_verified', true);

            return $next($request);
        }

        // Prevent MFA re-verification from extending session beyond maximum lifetime
        $sessionLifetime = config('security.session.lifetime', 480) * 60; // Convert to seconds
        $sessionCreatedAt = $request->session()->get('_session_created_at', now()->timestamp);
        $sessionElapsed = now()->timestamp - $sessionCreatedAt;

        if ($sessionElapsed >= $sessionLifetime) {
            // Session has already reached maximum lifetime, require full re-auth
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Session expired, please re-authenticate',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        // Redirect to MFA verification
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'MFA verification required',
                'redirect' => route('mfa.verify'),
            ], 403);
        }

        return redirect()->route('mfa.verify');
    }
}
