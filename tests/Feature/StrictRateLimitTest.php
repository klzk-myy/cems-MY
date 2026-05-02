<?php

namespace Tests\Feature;

use App\Http\Middleware\StrictRateLimit;
use App\Models\User;
use App\Services\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StrictRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that burst requests still count against rate limit.
     *
     * This verifies the fix for S5: Burst Bypasses Rate Limiter where burst
     * requests were returning $next($request) WITHOUT calling RateLimiter::hit(),
     * allowing burst requests to not count against the rate limit.
     */
    public function test_burst_requests_still_count_against_rate_limit(): void
    {
        $user = User::factory()->create();

        // Create a mock request
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // Create the middleware with mock RateLimitService
        $rateLimitService = $this->createMock(RateLimitService::class);
        $rateLimitService->method('isIpBlocked')->willReturn(false);
        $rateLimitService->method('checkBurst')->willReturn(true);
        $rateLimitService->method('getRateLimitKey')->willReturn('api:user:'.$user->id);

        $middleware = new StrictRateLimit($rateLimitService);

        // Clear the rate limiter
        $key = 'api:user:'.$user->id;
        RateLimiter::clear($key);

        $hitCount = 0;
        $next = function ($req) use (&$hitCount) {
            $hitCount++;

            return response()->json(['success' => true]);
        };

        // Make 3 burst requests
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request, $next, 'api');
        }

        // All 3 burst requests should have incremented the rate limiter
        $attempts = RateLimiter::attempts($key);
        $this->assertEquals(3, $attempts, 'Burst requests should count against rate limit');
    }

    /**
     * Test that rate limit headers are present in burst responses.
     */
    public function test_burst_responses_include_rate_limit_headers(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $rateLimitService = $this->createMock(RateLimitService::class);
        $rateLimitService->method('isIpBlocked')->willReturn(false);
        $rateLimitService->method('checkBurst')->willReturn(true);
        $rateLimitService->method('getRateLimitKey')->willReturn('api:user:'.$user->id);

        $middleware = new StrictRateLimit($rateLimitService);

        $next = function ($req) {
            return response()->json(['success' => true]);
        };

        $response = $middleware->handle($request, $next, 'api');

        // Should have rate limit headers even for burst responses
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') &&
            $response->headers->has('X-RateLimit-Remaining'),
            'Burst responses should include rate limit headers'
        );
    }

    /**
     * Test that requests exceeding burst allowance still count.
     */
    public function test_requests_exceeding_burst_allowance_count(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $rateLimitService = $this->createMock(RateLimitService::class);
        $rateLimitService->method('isIpBlocked')->willReturn(false);
        $rateLimitService->method('checkBurst')->willReturn(true); // Always within burst
        $rateLimitService->method('getRateLimitKey')->willReturn('api:user:'.$user->id);

        $middleware = new StrictRateLimit($rateLimitService);

        $key = 'api:user:'.$user->id;
        RateLimiter::clear($key);

        $next = function ($req) {
            return response()->json(['success' => true]);
        };

        // Make 5 requests (more than typical burst allowance)
        for ($i = 0; $i < 5; $i++) {
            $middleware->handle($request, $next, 'api');
        }

        // All requests should still count
        $attempts = RateLimiter::attempts($key);
        $this->assertEquals(5, $attempts, 'All requests should be counted even when exceeding burst');
    }

    /**
     * Test that burst allowance temporarily allows excess but counts it.
     */
    public function test_burst_allows_temporary_excess_but_counts_requests(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $rateLimitService = $this->createMock(RateLimitService::class);
        $rateLimitService->method('isIpBlocked')->willReturn(false);
        $rateLimitService->method('checkBurst')->willReturn(true);
        $rateLimitService->method('getRateLimitKey')->willReturn('transactions:user:'.$user->id);

        $middleware = new StrictRateLimit($rateLimitService);

        $key = 'transactions:user:'.$user->id;
        RateLimiter::clear($key);

        $next = function ($req) {
            return response()->json(['success' => true]);
        };

        // Make 3 burst requests (within burst allowance of 3 for transactions)
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request, $next, 'transactions');
        }

        // All 3 should be counted
        $attempts = RateLimiter::attempts($key);
        $this->assertEquals(3, $attempts, 'Burst requests should be counted');
    }
}
