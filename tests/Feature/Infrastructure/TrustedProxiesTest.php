<?php

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\TrustProxies;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    #[Test]
    public function trust_proxies_middleware_uses_env_configuration(): void
    {
        // Set a test value for TRUSTED_PROXIES across all possible env sources
        // that Laravel's env() helper checks: getenv, $_ENV, $_SERVER.
        putenv('TRUSTED_PROXIES=192.168.1.1');
        $_ENV['TRUSTED_PROXIES'] = '192.168.1.1';
        $_SERVER['TRUSTED_PROXIES'] = '192.168.1.1';

        $middleware = new TrustProxies;

        $reflection = new \ReflectionProperty($middleware, 'proxies');
        $reflection->setAccessible(true);
        $proxies = $reflection->getValue($middleware);

        $this->assertSame('192.168.1.1', $proxies);
    }
}
