<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Rules\PasswordComplexityRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    /**
     * Display the change password form.
     */
    public function show(): View
    {
        return view('auth.change-password');
    }

    /**
     * Handle a password change request.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordComplexityRule,
            ],
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password_hash)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ])->onlyInput('current_password');
        }

        // Update password
        $user->password = $request->password;
        $user->save();

        // Log password change
        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'description' => 'User changed their password',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('status', 'Password updated successfully.');
    }
}
