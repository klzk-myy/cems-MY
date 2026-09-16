<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Branch scope for rate reads and writes: users who can manage all branches
 * may target any branch (or the company-wide card when no branch_id is
 * given); every other role is limited to its own branch. Without this the
 * endpoints resolved to an unscoped set that mixed every branch's overrides.
 */
trait ResolvesBranchScope
{
    protected function resolveBranchId(?User $user, Request $request): ?int
    {
        if ($user?->role->canManageAllBranches() && $request->filled('branch_id')) {
            return (int) $request->input('branch_id');
        }

        // Branch scope: branch-scoped roles always operate on their own
        // branch's rates; an unassigned user falls back to company-wide.
        return $user?->branch_id;
    }
}
