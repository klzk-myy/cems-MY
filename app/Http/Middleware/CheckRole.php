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

        // Check if user has any of the required roles. Role names are
        // compile-time constants in route files, so an unrecognized name is
        // a developer error — throw loudly instead of silently failing
        // closed. All names are evaluated (no early break) so a typo throws
        // even when another listed role would match.
        //
        // Module aliases (compliance, accountant, accounting, users) are
        // effective-permission checks — the role's static ceiling AND the
        // role_permissions matrix — so an admin revocation closes the whole
        // module rather than only the policy-level checks. The plain role
        // aliases (admin, manager, teller) are identity checks.
        $hasRole = false;
        foreach ($roles as $role) {
            $matched = match ($role) {
                'admin' => $user->isAdmin(),
                'manager' => $user->isManager(),
                'compliance' => $user->role->canAccessCompliance(),
                'accountant' => $user->isAccountant() && $user->role->canAccessAccounting(),
                'accounting' => $user->role->canAccessAccounting(),
                'users' => $user->role->canManageUsers(),
                'teller' => $user->isTeller(),
                default => throw new \InvalidArgumentException(
                    "Unknown role [{$role}] in role middleware for {$request->path()}"
                ),
            };
            $hasRole = $hasRole || $matched;
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
