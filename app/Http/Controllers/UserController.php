<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UserController
 *
 * Handles user management operations including creation, updates, and deletion.
 * All methods require admin authentication.
 *
 * All business logic is delegated to UserService to maintain proper MVC separation.
 */
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a paginated listing of all users.
     *
     * @return View
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
     * @return View
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
     * @return RedirectResponse
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

        $user = $this->userService->createUser($validated, auth()->id());

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} created successfully!");
    }

    /**
     * Display the specified user's details.
     *
     * @return View
     */
    public function show(User $user)
    {
        $this->requireAdmin();

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing a user.
     *
     * @return View
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
     * @return RedirectResponse
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

        $user = $this->userService->updateUser($user, $validated, auth()->id());

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} updated successfully!");
    }

    /**
     * Remove the specified user
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request, User $user)
    {
        $this->requireAdmin();

        try {
            $this->userService->deleteUser($user, auth()->id());

            return redirect()->route('users.index')
                ->with('success', "User {$user->username} deleted successfully!");
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('users.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reset user password
     *
     * @return RedirectResponse
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

        $this->userService->resetPassword($user, $validated['password'], auth()->id());

        return redirect()->route('users.index')
            ->with('success', "Password for {$user->username} has been reset!");
    }

    /**
     * Toggle user active status
     *
     * @return RedirectResponse
     */
    public function toggleActive(Request $request, User $user)
    {
        $this->requireAdmin();

        try {
            $user = $this->userService->toggleActive($user, auth()->id());

            $status = $user->is_active ? 'activated' : 'deactivated';

            return redirect()->route('users.index')
                ->with('success', "User {$user->username} has been {$status}!");
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('users.index')
                ->with('error', $e->getMessage());
        }
    }
}
