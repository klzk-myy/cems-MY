<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\System\RateLimitService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\JsonResponse;
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
        foreach ($this->rateLimitDefinitions() as $name => $definition) {
            RateLimiter::for($name, function (Request $request) use ($name, $definition) {
                // Attempts/per_minutes resolve lazily per request — config may
                // be overridden after provider boot (tests, per-tenant tuning).
                return Limit::perMinutes(value($definition['per_minutes']), value($definition['attempts']))
                    ->by(($definition['key'])($request))
                    ->response(fn () => $this->rateLimitedResponse($request, $name, $definition));
            });
        }
    }

    /**
     * Limiter definitions: bucket key, attempts/window (resolved from
     * config('security.rate_limits.<name>') where configured), and the
     * 429 payload. `onLimited` hooks run extra bookkeeping — e.g. login
     * records a failed attempt — before the hit is logged.
     *
     * @return array<string, array{
     *     key: callable(Request): string,
     *     attempts: int|callable(): int,
     *     per_minutes: int|callable(): int,
     *     error: string,
     *     message: string,
     *     code: string,
     *     onLimited?: callable(Request): void
     * }>
     */
    private function rateLimitDefinitions(): array
    {
        $userOrIp = fn (Request $request) => (string) ($request->user()->id ?? $request->ip());

        return [
            // API general: 30 per minute per IP.
            'api' => [
                'key' => fn (Request $request) => (string) $request->ip(),
                'attempts' => fn () => (int) config('security.rate_limits.api.attempts', 30),
                'per_minutes' => 1,
                'error' => 'Too many requests',
                'message' => 'API rate limit exceeded. Please try again later.',
                'code' => 'RATE_LIMIT_EXCEEDED',
            ],
            // Login keys on IP AND submitted username so both shared-NAT
            // clients and credential-stuffing via rotating proxies are
            // constrained; each hit also counts as a failed attempt.
            'login' => [
                'key' => fn (Request $request) => $request->ip().'|'.strtolower((string) $request->input('username')),
                'attempts' => fn () => (int) config('security.rate_limits.login.attempts', 5),
                'per_minutes' => 1,
                'error' => 'Too many login attempts',
                'message' => 'Too many login attempts. Please try again later.',
                'code' => 'LOGIN_RATE_LIMIT_EXCEEDED',
                'onLimited' => fn (Request $request) => app(RateLimitService::class)->recordFailedAttempt($request->ip()),
            ],
            // Password reset: 5 per minute per IP (prevents mail bombing).
            'password-reset' => [
                'key' => fn (Request $request) => (string) $request->ip(),
                'attempts' => 5,
                'per_minutes' => 1,
                'error' => 'Too many password reset requests',
                'message' => 'Too many password reset attempts. Please try again later.',
                'code' => 'PASSWORD_RESET_RATE_LIMIT_EXCEEDED',
            ],
            // Transactions: 10 per minute per user.
            'transactions' => [
                'key' => $userOrIp,
                'attempts' => fn () => (int) config('security.rate_limits.transactions.attempts', 10),
                'per_minutes' => 1,
                'error' => 'Transaction rate limit exceeded',
                'message' => 'Too many transaction attempts. Please try again later.',
                'code' => 'TRANSACTION_RATE_LIMIT_EXCEEDED',
            ],
            // Bulk operations: 1 per 5 minutes per user.
            'bulk' => [
                'key' => $userOrIp,
                'attempts' => fn () => (int) (config('security.rate_limits.bulk.attempts') ?? 1),
                'per_minutes' => fn () => (int) (config('security.rate_limits.bulk.per_minutes') ?? 5),
                'error' => 'Bulk operation rate limit exceeded',
                'message' => 'Bulk operations are limited. Please try again later.',
                'code' => 'BULK_RATE_LIMIT_EXCEEDED',
            ],
            // Export operations: 5 per minute per user.
            'export' => [
                'key' => $userOrIp,
                'attempts' => fn () => (int) config('security.rate_limits.export.attempts', 5),
                'per_minutes' => 1,
                'error' => 'Export rate limit exceeded',
                'message' => 'Too many export attempts. Please try again later.',
                'code' => 'EXPORT_RATE_LIMIT_EXCEEDED',
            ],
            // Sensitive operations (MFA, password change): 3 per minute per user.
            'sensitive' => [
                'key' => $userOrIp,
                'attempts' => fn () => (int) config('security.rate_limits.sensitive.attempts', 3),
                'per_minutes' => 1,
                'error' => 'Sensitive operation rate limit exceeded',
                'message' => 'Too many sensitive operation attempts. Please try again later.',
                'code' => 'SENSITIVE_RATE_LIMIT_EXCEEDED',
            ],
        ];
    }

    /**
     * Shared 429 response for every limiter: optional bookkeeping hook,
     * hit logging, then the JSON payload.
     *
     * @param  array{error: string, message: string, code: string, onLimited?: callable(Request): void}  $definition
     */
    private function rateLimitedResponse(Request $request, string $limiter, array $definition): JsonResponse
    {
        $rateLimits = app(RateLimitService::class);

        if (isset($definition['onLimited'])) {
            ($definition['onLimited'])($request);
        }

        $rateLimits->logRateLimitHit($request, $limiter);

        return response()->json([
            'error' => $definition['error'],
            'message' => $definition['message'],
            'code' => $definition['code'],
        ], 429);
    }

    /**
     * Configure application routes.
     */
    private function configureRoutes(): void
    {
        $this->routes(function () {
            Route::prefix('api/v1/webhooks')
                ->middleware(['api'])
                ->group(base_path('routes/webhooks.php'));

            Route::prefix('api/v1')
                ->middleware(['api'])
                ->group(base_path('routes/api_v1.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
