<?php

namespace Tests\Unit\System;

use App\Services\System\RateLimitService;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateLimitServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['security.ip_blocking.enabled' => true]);
        config(['security.ip_blocking.whitelist' => []]);
        config(['security.ip_blocking.failed_attempts_threshold' => 3]);
        config(['security.ip_blocking.time_window_minutes' => 5]);
        config(['security.ip_blocking.block_duration_minutes' => 60]);
        config(['security.ip_blocking.max_block_duration_minutes' => 1440]);

        Cache::store(config('ratelimit.store'))->flush();
    }

    #[Test]
    public function block_ip_does_nothing_when_ip_blocking_disabled(): void
    {
        config(['security.ip_blocking.enabled' => false]);

        $service = new RateLimitService;
        $service->blockIp('192.168.1.1');

        $this->assertFalse($service->isIpBlocked('192.168.1.1'));
        $this->assertEmpty($service->getBlockedIps());
    }

    #[Test]
    public function record_failed_attempt_does_nothing_when_ip_blocking_disabled(): void
    {
        config(['security.ip_blocking.enabled' => false]);

        $service = new RateLimitService;
        $service->recordFailedAttempt('192.168.1.2');
        $service->recordFailedAttempt('192.168.1.2');
        $service->recordFailedAttempt('192.168.1.2');

        $this->assertSame(0, $service->getFailedAttempts('192.168.1.2'));
        $this->assertFalse($service->isIpBlocked('192.168.1.2'));
    }

    #[Test]
    public function record_failed_attempt_auto_blocks_when_threshold_exceeded(): void
    {
        $service = new RateLimitService;
        $service->recordFailedAttempt('192.168.1.3');
        $service->recordFailedAttempt('192.168.1.3');

        $this->assertFalse($service->isIpBlocked('192.168.1.3'));

        $service->recordFailedAttempt('192.168.1.3');

        $this->assertTrue($service->isIpBlocked('192.168.1.3'));
    }

    #[Test]
    public function unblock_ip_removes_from_blocked_list(): void
    {
        $service = new RateLimitService;
        $service->blockIp('192.168.1.4');

        $this->assertTrue($service->isIpBlocked('192.168.1.4'));

        $service->unblockIp('192.168.1.4');

        $this->assertFalse($service->isIpBlocked('192.168.1.4'));
        $this->assertEmpty($service->getBlockedIps());
    }

    #[Test]
    public function get_blocked_ips_returns_block_info(): void
    {
        $service = new RateLimitService;
        $service->blockIp('192.168.1.5');

        $blocked = $service->getBlockedIps();

        $this->assertCount(1, $blocked);
        $this->assertSame('192.168.1.5', $blocked[0]['ip']);
        $this->assertArrayHasKey('blocked_at', $blocked[0]);
        $this->assertArrayHasKey('duration_minutes', $blocked[0]);
    }

    #[Test]
    public function blocked_ips_index_supports_multiple_ips(): void
    {
        $service = new RateLimitService;
        $service->blockIp('192.168.1.10');
        $service->blockIp('192.168.1.11');

        $blocked = $service->getBlockedIps();
        $ips = array_column($blocked, 'ip');

        $this->assertCount(2, $blocked);
        $this->assertContains('192.168.1.10', $ips);
        $this->assertContains('192.168.1.11', $ips);
    }

    #[Test]
    public function blocked_ips_index_uses_redis_set_when_available(): void
    {
        $store = Cache::store(config('ratelimit.store'))->getStore();

        if (! $store instanceof RedisStore) {
            $this->markTestSkipped('Redis cache store is not configured.');
        }

        config(['ratelimit.store' => 'redis']);
        Cache::store('redis')->flush();

        $service = new RateLimitService;
        $service->blockIp('10.0.0.1');
        $service->blockIp('10.0.0.2');

        $members = Cache::store('redis')->getStore()->connection()->smembers('ip_block:all');
        sort($members);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $members);

        $service->unblockIp('10.0.0.1');

        $members = Cache::store('redis')->getStore()->connection()->smembers('ip_block:all');
        sort($members);

        $this->assertSame(['10.0.0.2'], $members);
    }
}
