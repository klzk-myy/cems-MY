<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Permission;
use Illuminate\Http\JsonResponse;

/**
 * Helpers for returning a 403 JSON response when the authenticated user's
 * role does not hold a permission in the role_permissions matrix.
 *
 * Host controllers must provide an `errorResponse()` helper (e.g. by using
 * the `ApiResponse` trait).
 */
trait RequiresPermission
{
    /**
     * Return a standardized 403 API response if the current user's role
     * does not hold the given permission in the role_permissions matrix.
     *
     * Requires the host controller to provide an `errorResponse()` method
     * (typically via the `ApiResponse` trait).
     */
    protected function requirePermissionResponse(Permission $permission, ?string $message = null): ?JsonResponse
    {
        $user = auth()->user();

        if ($user && $user->role->canPerform($permission)) {
            return null;
        }

        return $this->errorResponse(
            $message ?? "Unauthorized. {$permission->label()} required.",
            [],
            403
        );
    }
}
