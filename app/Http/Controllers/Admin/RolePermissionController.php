<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\System\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $this->requireAdmin();

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
     * Update the role-permission matrix from the checkbox grid.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $validated = $this->validateUpdateRequest($request);
        $updatedBy = (int) auth()->id();

        foreach (UserRole::cases() as $role) {
            $permissions = $validated[$role->value] ?? [];
            $this->permissionService->updateRolePermissions(
                $role,
                $permissions,
                $updatedBy
            );
        }

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

        $result = [];
        foreach ($validRoleValues as $roleValue) {
            $result[$roleValue] = [];
            foreach ($validPermissionKeys as $permKey) {
                $checkboxName = "permissions.{$roleValue}.{$permKey}";
                $result[$roleValue][$permKey] = $request->has($checkboxName);
            }
        }

        return $result;
    }
}
