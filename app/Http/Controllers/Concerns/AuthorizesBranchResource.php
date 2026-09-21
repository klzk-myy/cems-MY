<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\Domain\PermissionDeniedException;
use App\Models\Branch;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Branch-level authorization. Roles with the ManageAllBranches capability
 * may access any branch; other roles are restricted to their own branch.
 * Resources without a branch_id (null) are denied for those roles — only
 * all-branch roles may act on them.
 *
 * Accessors:
 *   - `authorizeBranchAccess(int $branchId)` — when only the ID is available.
 *   - `authorizeBranchResource(Model $resource, ...)` — when you have the
 *     full model (looks up `branch_id` via `getAttribute`, or the primary key
 *     if the resource is a `Branch` itself).
 *   - `authorizeAssignedBranch()` — gates on the user's own branch
 *     assignment rather than a resource's branch, for company-wide
 *     resources (e.g. customers).
 *
 * All accessors throw on denial: AuthenticationException (401) when
 * unauthenticated, PermissionDeniedException (403) otherwise — rendered
 * through the standard DomainException envelope.
 */
trait AuthorizesBranchResource
{
    protected function authorizeBranchAccess(int $branchId): void
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if ($user->role->canManageAllBranches()) {
            return;
        }

        if ((int) $branchId !== (int) $user->branch_id) {
            throw new PermissionDeniedException('access this branch', 'You do not have permission to access this branch.');
        }
    }

    protected function authorizeBranchResource(
        Model $resource,
        string $action = 'access',
        ?string $message = null
    ): void {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if ($user->role->canManageAllBranches()) {
            return;
        }

        $resourceBranchId = $resource instanceof Branch
            ? $resource->getKey()
            : $resource->getAttribute('branch_id');

        // Legacy rows predating branch scoping carry a null branch_id and
        // therefore have no provable branch ownership: deny them for
        // non-admins instead of silently granting access to everyone.
        // (Admins were already allowed above.)
        if ($resourceBranchId === null || (int) $resourceBranchId !== (int) $user->branch_id) {
            throw new PermissionDeniedException(
                "{$action} resources",
                $message ?? "You can only {$action} resources for your own branch."
            );
        }
    }

    /**
     * Gate on the user's own branch assignment rather than a resource's
     * branch — for company-wide resources (e.g. customers). Branch
     * operating roles (teller, manager) with no assignment fail closed;
     * office roles legitimately operate without a branch and pass —
     * downstream checks (till, session, permission matrix) still apply.
     */
    protected function authorizeAssignedBranch(
        string $message = 'You are not authorized for this action.'
    ): void {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if ($user->role->canManageAllBranches()) {
            return;
        }

        if ($user->role->requiresBranch() && $user->branch_id === null) {
            throw new PermissionDeniedException('perform this action', $message);
        }
    }
}
