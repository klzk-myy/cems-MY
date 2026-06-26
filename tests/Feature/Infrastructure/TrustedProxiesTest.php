<?php

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\TrustProxies;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    #[Test]
    public function trust_proxies_middleware_uses_config_configuration(): void
    {
        config(['trustedproxy.proxies' => '192.168.1.1']);

        $middleware = new TrustProxies;

        $reflection = new \ReflectionProperty($middleware, 'proxies');
        $reflection->setAccessible(true);
        $proxies = $reflection->getValue($middleware);

        $this->assertSame('192.168.1.1', $proxies);
    }
}
