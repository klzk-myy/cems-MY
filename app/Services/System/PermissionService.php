<?php

namespace App\Services\System;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Services\AuditService;
use Illuminate\Support\Facades\Cache;

/**
 * PermissionService
 *
 * Provides cached access to the dynamic role-permission matrix stored in the
 * role_permissions table. The matrix is the authoritative grant set for
 * dynamic permissions: can() reports the matrix state (built-in defaults
 * merged with DB overrides) and UserRole::canPerform() checks it directly.
 * The admin UI toggles permissions via this service; checks read from cache
 * to avoid per-request DB queries.
 */
class PermissionService
{
    private const CACHE_KEY = 'role_permissions_matrix';

    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Check whether the matrix grants a permission to a role. The matrix
     * is seeded from the role's built-in defaults; UserRole::canPerform()
     * uses this state as the effective check.
     */
    public function can(UserRole $role, Permission|string $permission): bool
    {
        $key = is_string($permission) ? $permission : $permission->value;
        $matrix = $this->getMatrix();

        return ($matrix[$role->value][$key] ?? false) === true;
    }

    /**
     * Get all granted permission keys for a role.
     *
     * @return list<string>
     */
    public function grantedPermissions(UserRole $role): array
    {
        $matrix = $this->getMatrix();
        $permissions = $matrix[$role->value] ?? [];

        return array_keys(array_filter($permissions));
    }

    /**
     * Get the full role-permission matrix.
     * Returns [role => [permission_key => bool]].
     *
     * @return array<string, array<string, bool>>
     */
    public function getMatrix(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $matrix = [];
            $defaults = Permission::defaultMatrix();

            // Initialize from the built-in defaults so an unseeded table
            // preserves the static role model — only an explicit stored
            // revocation can narrow it.
            foreach (UserRole::cases() as $role) {
                foreach (Permission::cases() as $permission) {
                    $matrix[$role->value][$permission->value] =
                        in_array($permission->value, $defaults[$role->value] ?? [], true);
                }
            }

            // Override with DB values. Rows for roles/permissions that no
            // longer exist in the enums are ignored rather than widening
            // the matrix.
            $dbPermissions = RolePermission::query()->get(['role', 'permission', 'granted']);
            foreach ($dbPermissions as $record) {
                if (isset($matrix[$record->role][$record->permission])) {
                    $matrix[$record->role][$record->permission] = (bool) $record->granted;
                }
            }

            return $matrix;
        });
    }

    /**
     * Update a single role-permission grant.
     * Logs the change to the audit trail and invalidates the cache.
     */
    public function updatePermission(
        UserRole $role,
        Permission $permission,
        bool $granted,
        int $updatedBy
    ): void {
        $oldValue = $this->can($role, $permission);

        if ($oldValue === $granted) {
            return; // No change
        }

        RolePermission::updateOrCreate(
            ['role' => $role->value, 'permission' => $permission->value],
            ['granted' => $granted, 'updated_by' => $updatedBy]
        );

        $this->auditService->log(
            'role_permission_updated',
            $updatedBy,
            'RolePermission',
            null,
            [
                'role' => $role->value,
                'permission' => $permission->value,
                'old_granted' => $oldValue,
                'new_granted' => $granted,
            ],
            [
                'role' => $role->value,
                'permission' => $permission->value,
                'granted' => $granted,
            ]
        );

        $this->clearCache();
    }

    /**
     * Bulk update permissions for a role from a checkbox grid.
     *
     * @param  array<string, bool>  $permissions  [permission_key => granted]
     */
    public function updateRolePermissions(
        UserRole $role,
        array $permissions,
        int $updatedBy
    ): void {
        $changes = [];

        foreach ($permissions as $key => $granted) {
            $permission = Permission::tryFrom($key);
            if ($permission === null) {
                continue;
            }

            $oldValue = $this->can($role, $permission);
            $granted = (bool) $granted;

            if ($oldValue === $granted) {
                continue;
            }

            $changes[] = [
                'permission' => $permission->value,
                'old' => $oldValue,
                'new' => $granted,
            ];

            RolePermission::updateOrCreate(
                ['role' => $role->value, 'permission' => $permission->value],
                ['granted' => $granted, 'updated_by' => $updatedBy]
            );
        }

        if (! empty($changes)) {
            $this->auditService->log(
                'role_permissions_bulk_updated',
                $updatedBy,
                'RolePermission',
                null,
                ['role' => $role->value, 'changes' => $changes],
                ['role' => $role->value, 'change_count' => count($changes)]
            );
        }

        $this->clearCache();
    }

    /**
     * Restore every role to the built-in default matrix, auditing each
     * role's change set. Used by the admin UI's "Default" action.
     */
    public function resetToDefaults(int $updatedBy): void
    {
        $defaults = Permission::defaultMatrix();

        foreach (UserRole::cases() as $role) {
            $permissions = [];
            foreach (Permission::cases() as $permission) {
                $permissions[$permission->value] = in_array(
                    $permission->value,
                    $defaults[$role->value] ?? [],
                    true
                );
            }

            $this->updateRolePermissions($role, $permissions, $updatedBy);
        }
    }

    /**
     * Seed the default permission matrix into the database.
     * Called by the seeder and during setup.
     */
    public function seedDefaults(): void
    {
        $matrix = Permission::defaultMatrix();

        foreach ($matrix as $roleValue => $permissionKeys) {
            $role = UserRole::tryFrom($roleValue);
            if ($role === null) {
                continue;
            }

            foreach (Permission::cases() as $permission) {
                $granted = in_array($permission->value, $permissionKeys, true);

                RolePermission::updateOrCreate(
                    ['role' => $role->value, 'permission' => $permission->value],
                    ['granted' => $granted]
                );
            }
        }

        $this->clearCache();
    }

    /**
     * Clear the permission cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
