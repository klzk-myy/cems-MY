<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\System\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * RolePermissionController
 *
 * Admin-only UI for managing the dynamic role-permission matrix.
 * Displays a grid of roles x permissions with checkboxes; saving
 * updates the role_permissions table via PermissionService (with
 * audit logging and cache invalidation).
 */
class RolePermissionController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Display the role-permission matrix with checkboxes.
     */
    public function index(): View
    {
        $this->requirePermission(Permission::ManageRolePermissions);

        $roles = UserRole::cases();
        $permissions = Permission::cases();
        $permissionsByCategory = Permission::groupedByCategory();
        $matrix = $this->permissionService->getMatrix();

        return view('admin.role-permissions.index', compact(
            'roles',
            'permissions',
            'permissionsByCategory',
            'matrix'
        ));
    }

    /**
     * Update the role-permission matrix from the checkbox grid, or restore
     * the built-in defaults when the Default action is submitted.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageRolePermissions);

        $updatedBy = (int) auth()->id();

        if ($request->input('action') === 'default') {
            $this->permissionService->resetToDefaults($updatedBy);

            return redirect()->route('admin.role-permissions.index')
                ->with('success', 'Role permissions restored to defaults.');
        }

        $validated = $this->validateUpdateRequest($request);

        // All-or-nothing: a failure mid-matrix must not leave a half-written
        // grant set (e.g. a stale enum rejecting one row after earlier writes).
        DB::transaction(function () use ($validated, $updatedBy) {
            foreach ($validated as $roleValue => $permissions) {
                $this->permissionService->updateRolePermissions(
                    UserRole::from($roleValue),
                    $permissions,
                    $updatedBy
                );
            }
        });

        return redirect()->route('admin.role-permissions.index')
            ->with('success', 'Role permissions updated successfully.');
    }

    /**
     * Validate the checkbox grid submission.
     * Each role has a set of permission keys; checked = granted.
     *
     * @return array<string, array<string, bool>>
     */
    private function validateUpdateRequest(Request $request): array
    {
        $validPermissionKeys = array_column(Permission::cases(), 'value');
        $validRoleValues = array_column(UserRole::cases(), 'value');
        $submitted = $request->input('permissions', []);
        $submitted = is_array($submitted) ? $submitted : [];

        $result = [];
        foreach ($validRoleValues as $roleValue) {
            // Only roles present in the submission are rewritten — a partial
            // payload must not silently revoke grants it never mentioned.
            if (! array_key_exists($roleValue, $submitted)) {
                continue;
            }
            $result[$roleValue] = [];
            foreach ($validPermissionKeys as $permKey) {
                $checkboxName = "permissions.{$roleValue}.{$permKey}";
                $result[$roleValue][$permKey] = $request->has($checkboxName);
            }
        }

        return $result;
    }
}
