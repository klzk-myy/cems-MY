<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Abort with 403 unless the authenticated user's role holds the given
     * permission in the role_permissions matrix. Module access is
     * matrix-driven: an admin grant unlocks the capability for any role.
     */
    protected function requirePermission(Permission $permission): void
    {
        $user = auth()->user();

        if (! $user || ! $user->role->canPerform($permission)) {
            abort(403, "Unauthorized. {$permission->label()} required.");
        }
    }

    /**
     * Abort with 403 unless the authenticated user has accounting access.
     * Covers managers, accountants, and admins — mirrors the role:accounting
     * route middleware for controller-level narrowing.
     */
    protected function requireAccountingAccess(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->role->canAccessAccounting()) {
            abort(403, 'Unauthorized. Accounting access required.');
        }
    }
}
