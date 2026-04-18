<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
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
            \App\Models\SystemLog::create([
                'user_id' => $user->id,
                'action' => 'login',
                'description' => 'User logged in successfully',
                'ip_address' => $request->ip(),
            ]);

            return redirect()->intended('/dashboard');
        }

        // Log failed login attempt
        if ($user) {
            \App\Models\SystemLog::create([
                'user_id' => $user->id,
                'action' => 'login_failed',
                'description' => 'Failed login attempt',
                'ip_address' => $request->ip(),
            ]);
        }

        return back()->withErrors([
            'username' => 'Invalid credentials.',
        ]);
    }

    public function logout(Request $request)
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
