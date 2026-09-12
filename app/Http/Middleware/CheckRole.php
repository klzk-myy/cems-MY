<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth()->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthorized'], 401)
                : redirect()->route('login');
        }

        // Check if user has any of the required roles
        $hasRole = false;
        foreach ($roles as $role) {
            $hasRole = match ($role) {
                'admin' => $user->isAdmin(),
                'manager' => $user->isManager(),
                'compliance', 'compliance_officer' => $user->isComplianceOfficer(),
                'accountant' => $user->role->isAccountant(),
                'teller' => $user->isTeller(),
                default => false,
            };

            if ($hasRole) {
                break;
            }
        }

        if (! $hasRole) {
            $this->auditService->logPermissionDenied(
                $request->path(),
                'role_check',
                'Missing required role: '.implode(',', $roles)
            );

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. You do not have permission to access this resource.'], 403);
            }

            abort(403, 'Unauthorized. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
