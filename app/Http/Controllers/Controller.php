<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Abort with 403 unless the authenticated user is a manager or admin.
     * UserRole::isManager() returns true for Manager and Admin roles.
     */
    protected function requireManagerOrAdmin(): void
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager or Admin access required.');
        }
    }

    /**
     * Abort with 403 unless the authenticated user is an admin.
     */
    protected function requireAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }
}
