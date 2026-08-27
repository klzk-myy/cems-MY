<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TestDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access in local and testing environments
        if (! app()->isLocal() && ! app()->runningUnitTests()) {
            abort(404);
        }

        return $next($request);
    }
}
