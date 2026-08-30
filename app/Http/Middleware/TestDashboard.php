<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test Dashboard Middleware
 *
 * Restricts access to test dashboard routes to local environment only.
 * This middleware ensures test utilities are never accessible in production.
 *
 * NOTE: runningUnitTests() is allowed to bypass the local-only check
 * to enable automated testing of test dashboard features.
 *
 * @see phpunit.xml - Test environment configuration
 */
class TestDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access in local and testing environments only
        // SECURITY: Never remove this check - test dashboards expose debug info
        if (! app()->isLocal() && ! app()->runningUnitTests()) {
            abort(404);
        }

        return $next($request);
    }
}
