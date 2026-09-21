<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Permission;
use App\Exceptions\Domain\PermissionDeniedException;

/**
 * Throws a PermissionDeniedException (403, standard error envelope) when the
 * authenticated user's role does not hold a permission in the
 * role_permissions matrix.
 */
trait RequiresPermission
{
    /**
     * Require the current user's role to hold the given permission, or throw.
     *
     * @throws PermissionDeniedException
     */
    protected function requirePermission(Permission $permission, ?string $message = null): void
    {
        $user = auth()->user();

        if ($user && $user->role->canPerform($permission)) {
            return;
        }

        throw new PermissionDeniedException(
            $permission->label(),
            $message ?? "Unauthorized. {$permission->label()} required."
        );
    }
}
