<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Services\System\PermissionService;
use Illuminate\Http\RedirectResponse;
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
    public function update(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageRolePermissions);

        $updatedBy = (int) auth()->id();

        if ($request->isDefaultReset()) {
            $this->permissionService->resetToDefaults($updatedBy);

            return redirect()->route('admin.role-permissions.index')
                ->with('success', 'Role permissions restored to defaults.');
        }

        $validated = $request->matrix();

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
}
