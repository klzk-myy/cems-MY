<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\RateLimitService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureRoutes();
    }

    /**
     * Configure rate limiting for the application.
     *
     * Implements BNM-compliant rate limits with stricter controls
     * and proper burst protection.
     */
    private function configureRateLimiting(): void
    {
        // API general rate limit: 30 per minute per IP (reduced from 60)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(
                config('security.rate_limits.api.attempts', 30)
            )->by($request->ip())->response(function () use ($request) {
                // Log rate limit hit
                app(RateLimitService::class)->logRateLimitHit($request, 'api');

                return response()->json([
                    'error' => 'Too many requests',
                    'message' => 'API rate limit exceeded. Please try again later.',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // Login rate limit: 5 attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(
                config('security.rate_limits.login.attempts', 5)
            )->by($request->ip())->response(function () use ($request) {
                // Record failed attempt for IP blocking
                app(RateLimitService::class)->recordFailedAttempt($request->ip());
                app(RateLimitService::class)->logRateLimitHit($request, 'login');

                return response()->json([
                    'error' => 'Too many login attempts',
                    'message' => 'Too many login attempts. Please try again later.',
                    'code' => 'LOGIN_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // Transaction rate limit: 10 per minute per user (reduced from 30)
        RateLimiter::for('transactions', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(
                config('security.rate_limits.transactions.attempts', 10)
            )->by($key)->response(function () use ($request) {
                app(RateLimitService::class)->logRateLimitHit($request, 'transactions');

                return response()->json([
                    'error' => 'Transaction rate limit exceeded',
                    'message' => 'Too many transaction attempts. Please try again later.',
                    'code' => 'TRANSACTION_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // STR submission rate limit: 3 per minute per user (reduced from 10)
        RateLimiter::for('str-submission', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(
                config('security.rate_limits.str.attempts', 3)
            )->by($key)->response(function () use ($request) {
                app(RateLimitService::class)->logRateLimitHit($request, 'str');

                return response()->json([
                    'error' => 'STR submission rate limit exceeded',
                    'message' => 'Too many STR submission attempts. Please try again later.',
                    'code' => 'STR_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // Bulk operations rate limit: 1 per 5 minutes per user
        RateLimiter::for('bulk', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            $config = config('security.rate_limits.bulk');

            return Limit::perMinutes(
                $config['per_minutes'] ?? 5,
                $config['attempts'] ?? 1
            )->by($key)->response(function () use ($request) {
                app(RateLimitService::class)->logRateLimitHit($request, 'bulk');

                return response()->json([
                    'error' => 'Bulk operation rate limit exceeded',
                    'message' => 'Bulk operations are limited. Please try again later.',
                    'code' => 'BULK_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // Export operations rate limit: 5 per minute per user
        RateLimiter::for('export', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(
                config('security.rate_limits.export.attempts', 5)
            )->by($key)->response(function () use ($request) {
                app(RateLimitService::class)->logRateLimitHit($request, 'export');

                return response()->json([
                    'error' => 'Export rate limit exceeded',
                    'message' => 'Too many export attempts. Please try again later.',
                    'code' => 'EXPORT_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });

        // Sensitive operations rate limit: 3 per minute per user
        RateLimiter::for('sensitive', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(
                config('security.rate_limits.sensitive.attempts', 3)
            )->by($key)->response(function () use ($request) {
                app(RateLimitService::class)->logRateLimitHit($request, 'sensitive');

                return response()->json([
                    'error' => 'Sensitive operation rate limit exceeded',
                    'message' => 'Too many sensitive operation attempts. Please try again later.',
                    'code' => 'SENSITIVE_RATE_LIMIT_EXCEEDED',
                ], 429);
            });
        });
    }

    /**
     * Configure application routes.
     */
    private function configureRoutes(): void
    {
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::prefix('api/v1')
                ->middleware(['api', 'auth:sanctum'])
                ->group(base_path('routes/api_v1.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
