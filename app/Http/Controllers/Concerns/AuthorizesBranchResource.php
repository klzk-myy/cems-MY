<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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
 *   - `authorizeBranchResourceOrAbort(Model $resource, ...)` — web-controller
 *     variant that aborts instead of returning a JSON denial.
 *   - `authorizeAssignedBranch()` — gates on the user's own branch
 *     assignment rather than a resource's branch, for company-wide
 *     resources (e.g. customers).
 *
 * The first two return a 403 `JsonResponse` when unauthorized, or a truthy
 * value (`true` / `null`) when authorized.
 */
trait AuthorizesBranchResource
{
    protected function authorizeBranchAccess(int $branchId): ?JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->denyResponse('Unauthenticated.', 401);
        }

        if ($user->role->canManageAllBranches()) {
            return null;
        }

        if ((int) $branchId !== (int) $user->branch_id) {
            return $this->denyResponse('You do not have permission to access this branch.', 403);
        }

        return null;
    }

    /**
     * @return true|JsonResponse True when authorized, a 403/401 response otherwise.
     */
    protected function authorizeBranchResource(
        Model $resource,
        string $action = 'access',
        ?string $message = null
    ): true|JsonResponse {
        $user = Auth::user();

        if ($user === null) {
            return $this->denyResponse('Unauthenticated.', 401);
        }

        if ($user->role->canManageAllBranches()) {
            return true;
        }

        $resourceBranchId = $resource instanceof Branch
            ? $resource->getKey()
            : $resource->getAttribute('branch_id');

        // Legacy rows predating branch scoping carry a null branch_id and
        // therefore have no provable branch ownership: deny them for
        // non-admins instead of silently granting access to everyone.
        // (Admins were already allowed above.)
        if ($resourceBranchId === null) {
            return $this->denyResponse(
                $message ?? "You can only {$action} resources for your own branch.",
                403
            );
        }

        if ((int) $resourceBranchId !== (int) $user->branch_id) {
            return $this->denyResponse(
                $message ?? "You can only {$action} resources for your own branch.",
                403
            );
        }

        return true;
    }

    /**
     * Web-controller variant of authorizeBranchResource(): aborts with the
     * denial status/message instead of returning a JSON response.
     */
    protected function authorizeBranchResourceOrAbort(
        Model $resource,
        string $action = 'access',
        ?string $message = null
    ): true {
        $result = $this->authorizeBranchResource($resource, $action, $message);

        if ($result instanceof JsonResponse) {
            abort($result->getStatusCode(), $result->getData(true)['message'] ?? 'Forbidden');
        }

        return true;
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
    ): ?JsonResponse {
        $user = Auth::user();

        if ($user === null) {
            return $this->denyResponse('Unauthenticated.', 401);
        }

        if ($user->role->canManageAllBranches()) {
            return null;
        }

        if ($user->role->requiresBranch() && $user->branch_id === null) {
            return $this->denyResponse($message, 403);
        }

        return null;
    }

    private function denyResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], $status);
    }
}
