<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function forgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink(
            $request->only('email')
        );

        // Neutral response for known and unknown addresses alike so this
        // endpoint cannot be used to enumerate registered emails.
        return back()->with(
            'status',
            __('If that email exists in our records, a password reset link has been sent.')
        );
    }

    public function showResetForm(Request $request, ?string $token = null): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => PasswordRules::forChange(
                User::where('email', (string) $request->input('email'))->first()
            ),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                // Assign the plain password: the User model's
                // setPasswordAttribute mutator performs the single hash.
                // Hashing here as well stored bcrypt(bcrypt(plain)) and made
                // every reset password unusable for login. Matches the
                // UserService::resetPassword write pattern; remember_token is
                // intentionally omitted because the users table has no such
                // column.
                $user->forceFill(['password' => $password])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
