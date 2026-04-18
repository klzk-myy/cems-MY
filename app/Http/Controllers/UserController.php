<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * UserController
 *
 * Handles user management operations including creation, updates, and deletion.
 * All methods require admin authentication.
 */
class UserController extends Controller
{
    /**
     * Check if current user has admin privileges.
     *
     * Throws 403 if user is not an admin.
     *
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    /**
     * Display a paginated listing of all users.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->requireAdmin();
        $users = User::paginate(20);

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     *
     * Displays role options and form for user creation.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $this->requireAdmin();
        $roles = [
            UserRole::Teller->value => UserRole::Teller->description(),
            UserRole::Manager->value => UserRole::Manager->description(),
            UserRole::ComplianceOfficer->value => UserRole::ComplianceOfficer->description(),
            UserRole::Admin->value => UserRole::Admin->description(),
        ];

        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created user in the database.
     *
     * Validates input, creates user with hashed password, and logs the action.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'password_confirmation' => 'required',
            'role' => ['required', Rule::in([
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        // Log user creation
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_created',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values' => [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} created successfully!");
    }

    /**
     * Display the specified user's details.
     *
     * @return \Illuminate\View\View
     */
    public function show(User $user)
    {
        $this->requireAdmin();

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing a user.
     *
     * @return \Illuminate\View\View
     */
    public function edit(User $user)
    {
        $this->requireAdmin();
        $roles = [
            UserRole::Teller->value => UserRole::Teller->label(),
            UserRole::Manager->value => UserRole::Manager->label(),
            UserRole::ComplianceOfficer->value => UserRole::ComplianceOfficer->label(),
            UserRole::Admin->value => UserRole::Admin->label(),
        ];

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in([
                UserRole::Teller->value,
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])],
            'is_active' => 'required|boolean',
        ]);

        $oldValues = [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];

        $user->update([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'],
        ]);

        // Log user update
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_updated',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => [
                'username' => $validated['username'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $validated['is_active'],
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} updated successfully!");
    }

    /**
     * Remove the specified user
     */
    public function destroy(Request $request, User $user)
    {
        $this->requireAdmin();

        // Debug logging
        \Log::debug('UserController@destroy', [
            'user_id' => $user->id,
            'user_is_admin' => $user->isAdmin(),
            'auth_id' => auth()->id(),
        ]);

        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete the last admin user!');
        }

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete your own account!');
        }

        $username = $user->username;
        $userId = $user->id;

        \Log::debug('UserController@destroy proceeding to delete', ['userId' => $userId]);

        $user->delete();

        \Log::debug('UserController@destroy deleted', ['userId' => $userId]);

        // Log user deletion
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_deleted',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => ['username' => $username],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User {$username} deleted successfully!");
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
        ]);

        $user->update([
            'password_hash' => Hash::make($validated['password']),
        ]);

        // Log password reset
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'password_reset',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('users.index')
            ->with('success', "Password for {$user->username} has been reset!");
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(Request $request, User $user)
    {
        $this->requireAdmin();

        // Prevent deactivating self
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot deactivate your own account!');
        }

        // Prevent deactivating last admin
        if ($user->isAdmin() && $user->is_active && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot deactivate the last active admin!');
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        // Log status toggle
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_status_toggled',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => ['is_active' => $oldStatus],
            'new_values' => ['is_active' => $user->is_active],
            'ip_address' => $request->ip(),
        ]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} has been {$status}!");
    }
}
