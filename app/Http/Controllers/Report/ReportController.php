<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;

abstract class ReportController extends Controller
{
    protected function requireManagerOrAdmin(): void
    {
        $user = auth()->user();
        $role = $user->role;

        if (! $role->canViewReports()) {
            abort(403, 'Unauthorized. Manager or Admin access required.');
        }
    }
}
