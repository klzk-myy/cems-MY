<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user && $user->is_active && Hash::check($request->password, $user->password_hash)) {
            Auth::login($user);
            $request->session()->regenerate();

            // Initialize session timeout tracking
            $request->session()->put('last_activity', time());

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Log successful login
            $this->auditService->logWithSeverity('login', [
                'user_id' => $user->id,
                'new_values' => ['message' => 'User logged in successfully'],
            ], 'INFO');

            return redirect()->intended('/dashboard');
        }

        // Log failed login attempt
        if ($user) {
            $this->auditService->logWithSeverity('login_failed', [
                'user_id' => $user->id,
                'new_values' => ['message' => 'Failed login attempt'],
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
