<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\PasswordComplexityRule;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

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
        $this->auditService->logWithSeverity('password_changed', [
            'user_id' => $user->id,
            'new_values' => ['message' => 'User changed their password'],
        ], 'INFO');

        return redirect()->back()->with('status', 'Password updated successfully.');
    }
}
