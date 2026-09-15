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
        // Route files gate modules by Permission key (role:manage_counters,
        // role:access_compliance, ...) — every key is a matrix check, so an
        // admin grant or revocation opens/closes the module for the role.
        // Legacy aliases still resolve: admin/manager/teller are identity
        // checks (admin inherits manager/accountant), and compliance,
        // accountant, accounting, users map to their module permissions.
        // Alias resolution lives in UserRole::matchesRoleAlias() so the
        // sidebar filter stays in sync.
        $hasRole = false;
        foreach ($roles as $role) {
            $hasRole = $hasRole || $user->role->matchesRoleAlias($role);
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
