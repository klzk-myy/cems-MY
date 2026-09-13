<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isAdmin = $user->role?->canManageAllBranches() ?? false;

            // Deny-by-default: an authenticated non-admin without a branch
            // assignment cannot prove which branch's data they may touch,
            // so they must not pass through unchecked.
            if (! $isAdmin && ! $user->branch_id) {
                abort(403, 'You do not have permission to access resources for this branch.');
            }

            $requestedBranchId = $request->route('branch')
                ?? $request->route('branchId')
                ?? $request->route('branch_id')
                ?? $request->input('branch_id');

            if ($requestedBranchId !== null
                && ! $isAdmin
                && (int) $requestedBranchId !== (int) $user->branch_id) {
                abort(403, 'You do not have permission to access resources for this branch.');
            }

            // Branch isolation on collection endpoints is enforced downstream
            // by the BranchScopedQuery concern / policies / services, which
            // read the caller's own branch — not a request value that could
            // be confused with a client-supplied filter.
        }

        return $next($request);
    }
}
