<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPasswordRequest;
use App\Support\PasswordHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Confirms the user's password before sensitive actions.
 *
 * The password.confirm middleware redirects here when
 * auth.password_confirmed_at is older than auth.password_timeout.
 */
class ConfirmablePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(ConfirmPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! PasswordHash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['password' => __('The provided password does not match our records.')]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard'));
    }
}
