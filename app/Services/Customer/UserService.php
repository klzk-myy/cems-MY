<?php

namespace App\Services\Customer;

use App\Enums\UserRole;
use App\Exceptions\Domain\UserManagementException;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * User Service
 *
 * Handles all user-related business logic including:
 * - User creation and updates
 * - Password hashing and reset
 * - Role assignment
 * - User activation/deactivation
 * - User deletion with validation
 * - Audit logging
 *
 * This service removes business logic from controllers and models,
 * ensuring proper MVC separation of concerns.
 */
class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new user with hashed password and role assignment.
     *
     * @param  array  $data  User data
     * @param  int  $createdBy  User ID creating the user
     * @return User Created user
     *
     * @throws UserManagementException If the acting user cannot assign the role
     */
    public function createUser(array $data, int $createdBy): User
    {
        $actor = User::findOrFail($createdBy);
        $role = $this->resolveAssignableRole($actor, $data['role'] ?? null);

        // Enforce the password policy here as well as in the form request —
        // this service is also reachable from commands and other callers
        // that never pass through HTTP validation.
        Validator::make($data, [
            'password' => PasswordRules::forNew(confirmed: false),
        ])->validate();

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'branch_id' => $actor->isAdmin() ? ($data['branch_id'] ?? null) : $actor->branch_id,
            'password' => $data['password'],
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        $user->role = $role;
        $user->save();

        // Seed history with the initial hash so reuse prevention covers it.
        PasswordHistory::record($user->id, $user->password_hash);

        // Log user creation
        $this->auditService->log(
            'user_created',
            $createdBy,
            'User',
            $user->id,
            [],
            [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
            ]
        );

        return $user;
    }

    /**
     * Update an existing user with role assignment.
     *
     * @param  User  $user  User to update
     * @param  array  $data  Updated user data
     * @param  int  $updatedBy  User ID updating the user
     * @return User Updated user
     *
     * @throws UserManagementException If the acting user cannot manage the
     *                                 target, cannot assign the requested role, or the change would
     *                                 remove the last active admin
     */
    public function updateUser(User $user, array $data, int $updatedBy): User
    {
        $actor = User::findOrFail($updatedBy);
        $newRole = $this->coerceRole($data['role'] ?? null);
        $newActive = (bool) ($data['is_active'] ?? $user->is_active);

        if ($actor->id === $user->id) {
            // Self-service profile edits never change the actor's own role
            // or deactivate the account.
            if ($newRole !== $user->role) {
                throw new UserManagementException('You cannot change your own role.');
            }

            if (! $newActive) {
                throw new UserManagementException('You cannot deactivate your own account.');
            }
        } else {
            $this->assertCanManageUser($actor, $user);
            $this->resolveAssignableRole($actor, $newRole);
        }

        $branchId = $actor->isAdmin()
            ? ($data['branch_id'] ?? null)
            : $actor->branch_id;

        $oldValues = [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'branch_id' => $user->branch_id,
        ];

        DB::transaction(function () use ($user, $newRole, $newActive, $data, $branchId) {
            $this->assertKeepsLastActiveAdmin($user, $newRole, $newActive);

            $user->update([
                'username' => $data['username'],
                'email' => $data['email'],
                'branch_id' => $branchId,
                'is_active' => $newActive,
            ]);

            $user->role = $newRole;
            $user->save();
        });

        // Log user update
        $this->auditService->log(
            'user_updated',
            $updatedBy,
            'User',
            $user->id,
            $oldValues,
            [
                'username' => $data['username'],
                'email' => $data['email'],
                'role' => $newRole->value,
                'is_active' => $newActive,
                'branch_id' => $branchId,
            ]
        );

        return $user->fresh();
    }

    /**
     * Delete a user with validation.
     *
     * Validates that:
     * - Not deleting the last admin
     * - Not deleting self
     *
     * @param  User  $user  User to delete
     * @param  int  $deletedBy  User ID deleting the user
     * @return bool True if deleted successfully
     *
     * @throws UserManagementException If validation fails
     */
    public function deleteUser(User $user, int $deletedBy): bool
    {
        // Prevent self-deletion
        if ($user->id === $deletedBy) {
            throw new UserManagementException('Cannot delete your own account.');
        }

        $this->assertCanManageUser(User::findOrFail($deletedBy), $user);

        $username = $user->username;
        $userId = $user->id;

        // The admin count and the delete must share a transaction: locking
        // the admin rows serializes concurrent deletes so two requests
        // cannot both pass the last-admin check.
        DB::transaction(function () use ($user) {
            // pluck + FOR UPDATE locks every admin row; a count() query
            // cannot take row locks, so count the locked ids in PHP.
            $admins = count(
                User::where('role', UserRole::Admin->value)->lockForUpdate()->pluck('id')->all()
            );

            if ($user->isAdmin() && $admins <= 1) {
                throw new UserManagementException('Cannot delete the last admin user.');
            }

            $user->delete();
        });

        // Log user deletion
        $this->auditService->log(
            'user_deleted',
            $deletedBy,
            'User',
            $userId,
            ['username' => $username],
            []
        );

        return true;
    }

    /**
     * Reset a user's password.
     *
     * @param  User  $user  User to reset password for
     * @param  string  $newPassword  New password
     * @param  int  $resetBy  User ID resetting the password
     * @return User Updated user
     */
    public function resetPassword(User $user, string $newPassword, int $resetBy): User
    {
        if ($user->id !== $resetBy) {
            $this->assertCanManageUser(User::findOrFail($resetBy), $user);
        }

        // Same policy as ResetPasswordRequest, enforced at the service layer
        // for non-HTTP callers.
        Validator::make(
            ['password' => $newPassword],
            ['password' => PasswordRules::forChange($user, confirmed: false)],
        )->validate();

        // Assign through the mutator so the superseded hash is archived,
        // password_changed_at is stamped, and the BNM forced-rotation clock
        // restarts for this user.
        $user->password = $newPassword;
        $user->save();

        // Log password reset
        $this->auditService->log(
            'password_reset',
            $resetBy,
            'User',
            $user->id,
            [],
            []
        );

        return $user->fresh();
    }

    /**
     * Normalize a role input (enum instance or string) to a UserRole.
     *
     * @throws UserManagementException If the value is not a valid role
     */
    private function coerceRole(mixed $role): UserRole
    {
        $resolved = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        if ($resolved === null) {
            throw new UserManagementException('A valid role is required.');
        }

        return $resolved;
    }

    /**
     * Resolve a role input to a UserRole the acting user is permitted to
     * assign, per UserRole::assignableRoles(). This is the service-level
     * enforcement behind the form-request whitelist — a forged request can
     * never escalate a user past the actor's assignable set.
     *
     * @throws UserManagementException If the role is invalid or unassignable
     */
    private function resolveAssignableRole(User $actor, mixed $role): UserRole
    {
        $resolved = $this->coerceRole($role);

        if (! in_array($resolved, $actor->role->assignableRoles(), true)) {
            throw new UserManagementException(
                "A {$actor->role->label()} cannot assign the {$resolved->label()} role."
            );
        }

        return $resolved;
    }

    /**
     * Whether the actor may administer the target account: admins manage
     * everyone; other roles only manage users in their own branch whose
     * current role is within their assignable set.
     */
    private function actorCanManage(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->branch_id !== null
            && $actor->branch_id === $target->branch_id
            && in_array($target->role, $actor->role->assignableRoles(), true);
    }

    /**
     * @throws UserManagementException If the actor cannot manage the target
     */
    private function assertCanManageUser(User $actor, User $target): void
    {
        if (! $this->actorCanManage($actor, $target)) {
            throw new UserManagementException(
                "A {$actor->role->label()} cannot manage {$target->role->label()} accounts."
            );
        }
    }

    /**
     * Guard against demoting or deactivating the last active admin, which
     * would lock the system out of privileged operations entirely.
     *
     * @throws UserManagementException If the change removes the last active admin
     */
    private function assertKeepsLastActiveAdmin(User $user, UserRole $newRole, bool $newActive): void
    {
        $leavesAdminPool = $user->role === UserRole::Admin
            && $user->is_active
            && ($newRole !== UserRole::Admin || ! $newActive);

        // The lock serializes concurrent demotes/deactivates: a second caller
        // waits on the locked admin rows, then recounts after commit. Without
        // it two requests could both see count() > 1 and zero out the pool.
        $activeAdmins = count(
            User::where('role', UserRole::Admin->value)
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id')
                ->all()
        );

        if ($leavesAdminPool && $activeAdmins <= 1) {
            throw new UserManagementException('Cannot demote or deactivate the last active admin.');
        }
    }
}
