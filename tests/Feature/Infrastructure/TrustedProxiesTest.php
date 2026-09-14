<?php

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
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

    #[Test]
    public function forwarded_for_is_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        Route::get('/__test-ip', fn () => response(request()->ip()));

        $response = $this->get('/__test-ip', ['X-Forwarded-For' => '203.0.113.99']);

        $this->assertSame('127.0.0.1', $response->getContent());
    }

    #[Test]
    public function forwarded_for_is_honored_when_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        Route::get('/__test-ip', fn () => response(request()->ip()));

        $response = $this->get('/__test-ip', ['X-Forwarded-For' => '203.0.113.99']);

        $this->assertSame('203.0.113.99', $response->getContent());
    }
}
