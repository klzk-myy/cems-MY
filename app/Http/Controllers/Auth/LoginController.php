<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutOtherDevicesRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\RateLimitService;
use App\Support\PasswordHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Well-formed bcrypt hash checked for unknown usernames so the response
     * timing matches a real credential check.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

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

        // Always run the hash check — short-circuiting on unknown usernames
        // leaks account existence through response timing. A hash stored in
        // a foreign format (e.g. bcrypt under the Argon2id driver) makes
        // Hash::check throw — PasswordHash fails closed as invalid
        // credentials rather than a 500 on the login page.
        $passwordValid = PasswordHash::check(
            $validated['password'],
            $user->password_hash ?? self::DUMMY_PASSWORD_HASH
        );

        if ($user && $user->is_active && $passwordValid) {
            try {
                DB::transaction(function () use ($user, $request) {
                    Auth::login($user, (bool) $request->boolean('remember'));
                    $request->session()->regenerate();
                    $request->session()->put('last_activity', time());
                    // Absolute session lifetime anchor read by
                    // EnsureMfaVerified — without it that check is dead code.
                    $request->session()->put('_session_created_at', time());
                    $user->update(['last_login_at' => now()]);
                    $this->rateLimitService->clearFailedAttempts($request->ip());
                });

                $this->auditService->logWithSeverity('login', [
                    'user_id' => $user->id,
                    'new_values' => ['message' => 'User logged in successfully'],
                ], 'INFO');

                // BNM password policy: force rotation when the password has
                // expired (or was never stamped).
                if ($user->passwordExpired()) {
                    return redirect()
                        ->route('password.change')
                        ->with('warning', 'Your password has expired and must be changed before continuing.');
                }

                return redirect()->intended('/dashboard');
            } catch (ValidationException|DomainException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Infrastructure failure (DB, session store) — not a
                // credential failure: no failed-attempt strike, and the
                // message must not mislead the user into retrying their
                // password.
                Log::error('Login transaction failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

                return back()->withErrors([
                    'username' => 'Login is temporarily unavailable. Please try again.',
                ]);
            }
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

    /**
     * Forced change-password form shown after login when the password has
     * expired under the BNM rotation policy.
     */
    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();

        if (! PasswordHash::check($validated['current_password'], $user->password_hash)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->password = $validated['password']; // mutator hashes + stamps password_changed_at
        $user->save();

        // Refresh this session's stored password hash so the current device
        // stays valid under auth.session; every other session fails its next
        // check against the new hash and is logged out.
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );

        $this->auditService->logWithSeverity('password_changed_forced_rotation', [
            'user_id' => $user->id,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ], 'INFO');

        return redirect('/dashboard')->with('success', 'Password updated successfully.');
    }

    /**
     * Invalidate every other session for the authenticated user after
     * re-verifying their current password.
     */
    public function logoutOtherDevices(LogoutOtherDevicesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = Auth::user();

        if (! PasswordHash::check($validated['current_password'], $user->password_hash)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Re-hash the same password directly on the column (bypassing the
        // password mutator) so the stored hash changes — every other session
        // fails validation against it under auth.session while this device
        // keeps working. Going through the mutator would record the current
        // hash to PasswordHistory and stamp password_changed_at, neither of
        // which is correct since the password itself is not changing.
        $user->password_hash = Hash::make($validated['current_password']);
        $user->save();

        // Refresh this device's stored hash so the current session stays
        // valid under the auth.session middleware, then rotate the CSRF
        // token. Only other devices are signed out.
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );
        $request->session()->regenerateToken();

        return back()->with('success', 'All other devices have been logged out.');
    }
}
