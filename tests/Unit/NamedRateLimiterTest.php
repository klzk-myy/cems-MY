<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\System\RateLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NamedRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The 429 response hooks log hits and record failed logins through
        // RateLimitService; stub it so invoking the callbacks stays pure.
        $rateLimits = Mockery::mock(RateLimitService::class);
        $rateLimits->shouldIgnoreMissing();
        $this->app->instance(RateLimitService::class, $rateLimits);
    }

    private function requestFor(?User $user = null, string $ip = '10.9.8.7'): Request
    {
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', $ip);

        if ($user !== null) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    #[Test]
    public function all_named_limiters_are_registered(): void
    {
        foreach (['api', 'login', 'password-reset', 'transactions', 'bulk', 'export', 'sensitive'] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), "Rate limiter '{$name}' is not registered");
        }
    }

    #[Test]
    public function each_limiter_returns_its_429_code(): void
    {
        $user = new User;
        $user->id = 42;

        $expected = [
            'api' => 'RATE_LIMIT_EXCEEDED',
            'login' => 'LOGIN_RATE_LIMIT_EXCEEDED',
            'password-reset' => 'PASSWORD_RESET_RATE_LIMIT_EXCEEDED',
            'transactions' => 'TRANSACTION_RATE_LIMIT_EXCEEDED',
            'bulk' => 'BULK_RATE_LIMIT_EXCEEDED',
            'export' => 'EXPORT_RATE_LIMIT_EXCEEDED',
            'sensitive' => 'SENSITIVE_RATE_LIMIT_EXCEEDED',
        ];

        foreach ($expected as $name => $code) {
            $request = $name === 'login'
                ? Request::create('/test', 'POST', ['username' => 'user'])
                : $this->requestFor($user);
            $request->server->set('REMOTE_ADDR', '10.9.8.7');

            $limit = call_user_func(RateLimiter::limiter($name), $request);
            $response = call_user_func($limit->responseCallback, $request);

            $this->assertSame(429, $response->getStatusCode(), "{$name} did not return 429");
            $this->assertSame($code, $response->getData(true)['code'], "{$name} returned the wrong code");
        }
    }

    #[Test]
    public function login_limiter_keys_on_ip_and_lowercased_username(): void
    {
        $request = Request::create('/test', 'POST', ['username' => 'Alice']);
        $request->server->set('REMOTE_ADDR', '10.1.2.3');

        $limit = call_user_func(RateLimiter::limiter('login'), $request);

        $this->assertSame('10.1.2.3|alice', $limit->key);
    }

    #[Test]
    public function bulk_limiter_uses_five_minute_window_and_one_attempt(): void
    {
        $limit = call_user_func(RateLimiter::limiter('bulk'), $this->requestFor());

        $this->assertSame(300, $limit->decaySeconds);
        $this->assertSame(1, $limit->maxAttempts);
    }

    #[Test]
    public function user_scoped_limiters_fall_back_to_ip_for_guests(): void
    {
        $limit = call_user_func(RateLimiter::limiter('transactions'), $this->requestFor());

        $this->assertSame('10.9.8.7', $limit->key);
    }
}
