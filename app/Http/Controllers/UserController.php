<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Customer\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * UserController
 *
 * Handles user management operations including creation, updates, and deletion.
 * Admins have company-wide access; managers are scoped to their own branch.
 *
 * All business logic is delegated to UserService to maintain proper MVC separation.
 */
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a paginated listing of users.
     * Admins see all users; managers see only users in their branch.
     */
    public function index(): View
    {
        $this->requireManagerOrAdmin();

        $query = User::with('branch');

        if (! auth()->user()->isAdmin()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $users = $query->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     *
     * Displays role options and form for user creation.
     */
    public function create(): View
    {
        $this->requireManagerOrAdmin();

        $roles = $this->assignableRolesForForm();
        $branches = $this->branchOptionsForForm();

        return view('users.create', compact('roles', 'branches'));
    }

    /**
     * Store a newly created user in the database.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        // Managers can only create users in their own branch
        if (! auth()->user()->isAdmin()) {
            $validated['branch_id'] = auth()->user()->branch_id;
        }

        $user = $this->userService->createUser($validated, (int) auth()->id());

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} created successfully!");
    }

    /**
     * Display the specified user's details.
     */
    public function show(User $user): View
    {
        $this->requireManagerOrAdmin();
        $this->authorize('view', $user);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): View
    {
        $this->requireManagerOrAdmin();
        $this->authorize('update', $user);

        $roles = $this->assignableRolesForForm($user);
        $branches = $this->branchOptionsForForm();

        return view('users.edit', compact('user', 'roles', 'branches'));
    }

    /**
     * Update the specified user in the database.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        // Managers cannot change a user's branch away from their own
        if (! auth()->user()->isAdmin()) {
            $validated['branch_id'] = auth()->user()->branch_id;
        }

        $user = $this->userService->updateUser($user, $validated, (int) auth()->id());

        return redirect()->route('users.index')
            ->with('success', "User {$user->username} updated successfully!");
    }

    /**
     * Reset user password
     */
    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $this->userService->resetPassword($user, $request->validated('password'), (int) auth()->id());

        return redirect()->route('users.index')
            ->with('success', "Password for {$user->username} has been reset!");
    }

    /**
     * Build the role options for the create/edit form from the acting user's
     * assignableRoles() set — the same source the request validation and
     * UserService enforce. When editing your own account only the current
     * role is offered, since role self-changes are rejected downstream.
     *
     * @return array<string, string>
     */
    private function assignableRolesForForm(?User $editing = null): array
    {
        $actor = auth()->user();

        $roles = $editing !== null && $editing->id === $actor->id
            ? [$actor->role]
            : $actor->role->assignableRoles();

        $options = [];
        foreach ($roles as $role) {
            $options[$role->value] = $role->label().' - '.$role->description();
        }

        return $options;
    }

    /**
     * Branch options for the create/edit form. Non-admin actors can only
     * operate within their own branch, so they are not offered others.
     *
     * @return Collection<int, Branch>
     */
    private function branchOptionsForForm()
    {
        $actor = auth()->user();

        return Branch::query()
            ->when(! $actor->isAdmin(), fn ($query) => $query->whereKey($actor->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
