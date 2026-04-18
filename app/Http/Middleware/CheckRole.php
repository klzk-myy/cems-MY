<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Check if user has any of the required roles
        $hasRole = false;
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
                break;
            }
        }

        if (! $hasRole) {
            abort(403, 'Unauthorized. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
