<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\RateLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected RateLimitService $rateLimitService
    ) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('username', $validated['username'])->first();

        if ($user && $user->is_active && Hash::check($validated['password'], $user->password_hash)) {
            Auth::login($user);
            $request->session()->regenerate();

            // Initialize session timeout tracking
            $request->session()->put('last_activity', time());

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Clear any previous failed login attempts for this IP
            $this->rateLimitService->clearFailedAttempts($request->ip());

            // Log successful login
            $this->auditService->logWithSeverity('login', [
                'user_id' => $user->id,
                'new_values' => ['message' => 'User logged in successfully'],
            ], 'INFO');

            return redirect()->intended('/dashboard');
        }

        // Record failed login attempt for IP-based auto-blocking
        // BNM requires rate limiting and brute-force protection on login endpoints
        $ip = $request->ip();
        $this->rateLimitService->recordFailedAttempt($ip);

        // Log failed login attempt
        if ($user) {
            $this->auditService->logWithSeverity('login_failed', [
                'user_id' => $user->id,
                'new_values' => ['message' => 'Failed login attempt for IP: '.$ip],
            ], 'WARNING');
        } else {
            // Log unknown username attempts too (potential reconnaissance)
            $this->auditService->logWithSeverity('login_failed_unknown_user', [
                'new_values' => [
                    'username' => $validated['username'],
                    'ip' => $ip,
                ],
            ], 'WARNING');
        }

        return back()->withErrors([
            'username' => 'Invalid credentials.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        // Clear MFA session data
        $request->session()->forget('mfa_verified');
        $request->session()->forget('mfa_verified_at');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
