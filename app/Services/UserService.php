<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

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
     */
    public function createUser(array $data, int $createdBy): User
    {
        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role' => $data['role'],
            'mfa_enabled' => false,
            'is_active' => true,
        ]);

        // Log user creation
        SystemLog::create([
            'user_id' => $createdBy,
            'action' => 'user_created',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values' => [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $user;
    }

    /**
     * Update an existing user with role assignment.
     *
     * @param  User  $user  User to update
     * @param  array  $data  Updated user data
     * @param  int  $updatedBy  User ID updating the user
     * @return User Updated user
     */
    public function updateUser(User $user, array $data, int $updatedBy): User
    {
        $oldValues = [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];

        $user->update([
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        // Log user update
        SystemLog::create([
            'user_id' => $updatedBy,
            'action' => 'user_updated',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => [
                'username' => $data['username'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => $data['is_active'],
            ],
            'ip_address' => request()->ip(),
        ]);

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
     * @throws \InvalidArgumentException If validation fails
     */
    public function deleteUser(User $user, int $deletedBy): bool
    {
        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            throw new \InvalidArgumentException('Cannot delete the last admin user!');
        }

        // Prevent self-deletion
        if ($user->id === $deletedBy) {
            throw new \InvalidArgumentException('Cannot delete your own account!');
        }

        $username = $user->username;
        $userId = $user->id;

        $user->delete();

        // Log user deletion
        SystemLog::create([
            'user_id' => $deletedBy,
            'action' => 'user_deleted',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'old_values' => ['username' => $username],
            'ip_address' => request()->ip(),
        ]);

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
        $user->update([
            'password_hash' => Hash::make($newPassword),
        ]);

        // Log password reset
        SystemLog::create([
            'user_id' => $resetBy,
            'action' => 'password_reset',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);

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
     * @throws \InvalidArgumentException If validation fails
     */
    public function toggleActive(User $user, int $toggledBy): User
    {
        // Prevent deactivating self
        if ($user->id === $toggledBy) {
            throw new \InvalidArgumentException('Cannot deactivate your own account!');
        }

        // Prevent deactivating last admin
        if ($user->isAdmin() && $user->is_active && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            throw new \InvalidArgumentException('Cannot deactivate the last active admin!');
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        // Log status toggle
        SystemLog::create([
            'user_id' => $toggledBy,
            'action' => 'user_status_toggled',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => ['is_active' => $oldStatus],
            'new_values' => ['is_active' => $user->is_active],
            'ip_address' => request()->ip(),
        ]);

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

        return true;
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

        return true;
    }

    /**
     * Get cached user permissions based on role.
     */
    public function getUserPermissions(int $userId): array
    {
        return Cache::remember(
            "user:{$userId}:permissions",
            now()->addHour(),
            fn () => $this->calculatePermissions($userId)
        );
    }

    /**
     * Calculate permissions for a user based on role.
     */
    protected function calculatePermissions(int $userId): array
    {
        $user = User::findOrFail($userId);
        $role = $user->role;

        return match ($role) {
            UserRole::Admin => ['*'],
            UserRole::ComplianceOfficer => ['transactions.view', 'compliance.*', 'reports.*'],
            UserRole::Manager => ['transactions.create', 'transactions.view', 'transactions.approve', 'reports.view'],
            UserRole::Teller => ['transactions.create', 'transactions.view'],
            default => [],
        };
    }
}
