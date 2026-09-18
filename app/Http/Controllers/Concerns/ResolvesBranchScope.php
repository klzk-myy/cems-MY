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

    /**
     * Branch scope for accounting reports: privileged users default to the
     * consolidated all-branch view (null) and may pick a single branch via
     * branch_id; every other role is pinned to its own branch and must have
     * one assigned — an unassigned branch user gets 403, never a silent
     * consolidated view.
     */
    protected function resolveReportBranchId(?User $user, Request $request): ?int
    {
        if ($user?->role->canManageAllBranches()) {
            return $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        }

        abort_if($user?->branch_id === null, 403, 'Your account is not assigned to a branch.');

        return $user->branch_id;
    }
}
