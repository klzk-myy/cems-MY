<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupAccessible
{
    /**
     * Handle an incoming request.
     *
     * Blocks access to setup routes in production after setup is complete.
     * Setup is considered complete when User, Currency, ExchangeRate, and Branch records exist.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $setupComplete = User::exists()
            && Currency::exists()
            && ExchangeRate::exists()
            && Branch::exists();

        if ($setupComplete && app()->environment('production')) {
            abort(404);
        }

        return $next($request);
    }
}
