<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckBranchAccess Middleware
 *
 * Enforces branch-level access control:
 * - Admin users can access any branch
 * - Non-admin users can only access their own branch (matching branch_id)
 * - Unauthenticated users get 401
 */
class CheckBranchAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$params  Route parameter names to check (default: branch, branch_id)
     */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $user = auth()->user();

        // If user is not authenticated, return 401
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Admin users can access any branch (pass through)
        if ($user->role === UserRole::Admin) {
            return $next($request);
        }

        // Determine which route parameters to check
        // Default to 'branch' and 'branch_id' if none specified
        $parameters = empty($params) ? ['branch', 'branch_id'] : $params;

        foreach ($parameters as $param) {
            $branchId = $request->route($param);

            // If this parameter exists in the route, check branch access
            if ($branchId !== null) {
                // User must have a branch_id to access branch-restricted routes
                if ($user->branch_id === null) {
                    return response()->json(['message' => 'Forbidden: No branch assigned'], 403);
                }

                // Check if user's branch matches the requested branch
                if ($user->branch_id !== (int) $branchId) {
                    return response()->json(['message' => 'Forbidden: Access denied to this branch'], 403);
                }

                // Found and validated the branch parameter, proceed
                return $next($request);
            }
        }

        // No branch parameter found in route, pass through
        return $next($request);
    }
}
