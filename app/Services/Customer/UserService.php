<?php

namespace App\Services\Customer;

use App\Enums\UserRole;
use App\Exceptions\Domain\UserManagementException;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\AuditService;

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

        $this->assertKeepsLastActiveAdmin($user, $newRole, $newActive);

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

        $user->update([
            'username' => $data['username'],
            'email' => $data['email'],
            'branch_id' => $branchId,
            'is_active' => $newActive,
        ]);

        $user->role = $newRole;
        $user->save();

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
        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            throw new UserManagementException('Cannot delete the last admin user.');
        }

        // Prevent self-deletion
        if ($user->id === $deletedBy) {
            throw new UserManagementException('Cannot delete your own account.');
        }

        $this->assertCanManageUser(User::findOrFail($deletedBy), $user);

        $username = $user->username;
        $userId = $user->id;

        $user->delete();

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
     * Toggle a user's active status with validation.
     *
     * Validates that:
     * - Not deactivating self
     * - Not deactivating last active admin
     *
     * @param  User  $user  User to toggle
     * @param  int  $toggledBy  User ID toggling the status
     * @return User Updated user
     *
     * @throws UserManagementException If validation fails
     */
    public function toggleActive(User $user, int $toggledBy): User
    {
        // Prevent deactivating self
        if ($user->id === $toggledBy) {
            throw new UserManagementException('Cannot deactivate your own account.');
        }

        // Prevent deactivating last admin
        if ($user->isAdmin() && $user->is_active && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            throw new UserManagementException('Cannot deactivate the last active admin.');
        }

        $this->assertCanManageUser(User::findOrFail($toggledBy), $user);

        $oldStatus = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        // Log status toggle
        $this->auditService->log(
            'user_status_toggled',
            $toggledBy,
            'User',
            $user->id,
            ['is_active' => $oldStatus],
            ['is_active' => $user->is_active]
        );

        return $user->fresh();
    }

    /**
     * Check if a user can be deleted.
     *
     * @param  User  $user  User to check
     * @param  int  $requesterId  User ID requesting deletion
     * @return bool True if user can be deleted
     */
    public function canDelete(User $user, int $requesterId): bool
    {
        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            return false;
        }

        // Prevent self-deletion
        if ($user->id === $requesterId) {
            return false;
        }

        $requester = User::find($requesterId);

        return $requester !== null && $this->actorCanManage($requester, $user);
    }

    /**
     * Check if a user's active status can be toggled.
     *
     * @param  User  $user  User to check
     * @param  int  $requesterId  User ID requesting toggle
     * @return bool True if status can be toggled
     */
    public function canToggleActive(User $user, int $requesterId): bool
    {
        // Prevent deactivating self
        if ($user->id === $requesterId) {
            return false;
        }

        // Prevent deactivating last admin
        if ($user->isAdmin() && $user->is_active && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            return false;
        }

        $requester = User::find($requesterId);

        return $requester !== null && $this->actorCanManage($requester, $user);
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

        if ($leavesAdminPool
            && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            throw new UserManagementException('Cannot demote or deactivate the last active admin.');
        }
    }
}
