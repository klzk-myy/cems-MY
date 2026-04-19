<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that allows access if user has ANY of the specified roles.
 * Both CheckRole and CheckRoleAny implement OR semantics (user needs ANY of the roles).
 * CheckRoleAny exists for semantic clarity when the intent is specifically "any of these roles".
 */
class CheckRoleAny
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  Role checks to perform (OR logic - user needs ANY one)
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Check if user has ANY of the required roles
        foreach ($roles as $role) {
            $hasRole = match ($role) {
                'admin' => $user->isAdmin(),
                'manager' => $user->isManager(),
                'compliance' => $user->isComplianceOfficer(),
                'compliance_officer' => $user->isComplianceOfficer(),
                'teller' => true, // All authenticated users are at least tellers
                default => false,
            };

            if ($hasRole) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized. You do not have permission to access this resource.');
    }
}
