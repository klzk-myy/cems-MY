<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * Enforces session timeout based on configuration in config/cems.php.
     * Default idle timeout is 15 minutes per BNM security compliance.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = config('cems.session_timeout_minutes', 15);
        $timeoutSeconds = $timeoutMinutes * 60;

        // Skip timeout check for certain paths (e.g., MFA setup, recovery)
        $excludedPaths = [
            'mfa/*',
            'logout',
            // The header bell polls this endpoint every 60s to keep badges
            // fresh. It must not stamp last_activity, otherwise an open tab
            // would silently defeat the idle-session-timeout control.
            'notifications/unread-count',
        ];

        foreach ($excludedPaths as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        // Check if session has last activity timestamp
        $lastActivity = $request->session()->get('last_activity');

        if ($lastActivity !== null) {
            $elapsed = now()->timestamp - $lastActivity;

            if ($elapsed >= $timeoutSeconds) {
                // Session has timed out
                $request->session()->forget('last_activity');
                $request->session()->forget('mfa_verified');
                $request->session()->forget('mfa_verified_at');

                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Session timeout',
                        'message' => "Your session has timed out after {$timeoutMinutes} minutes of inactivity. Please log in again.",
                    ], 401);
                }

                return redirect('/login')->with('error', "Session timed out after {$timeoutMinutes} minutes of inactivity.");
            }
        }

        // Update last activity timestamp (only for authenticated users)
        if (auth()->check()) {
            $request->session()->put('last_activity', now()->timestamp);

            // Anchor the absolute session lifetime for sessions that never
            // got stamped at login (remember-me restores, sessions predating
            // the login-side put). EnsureMfaVerified reads this value.
            if (! $request->session()->has('_session_created_at')) {
                $request->session()->put('_session_created_at', now()->timestamp);
            }
        }

        return $next($request);
    }
}
