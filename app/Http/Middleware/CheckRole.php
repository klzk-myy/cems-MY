<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
                'teller' => $user->role === UserRole::Teller,
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
