<?php

namespace Tests\Unit\Services\Security;

use App\Services\Security\IpValidationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IpValidationServiceTest extends TestCase
{
    private IpValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IpValidationService::class);
    }

    #[Test]
    public function blocklist_denies_blocked_ip(): void
    {
        $this->assertFalse(
            $this->service->isAllowed('192.168.1.1', [], ['192.168.1.1'])
        );
    }

    #[Test]
    public function empty_allowlist_allows_any_ip(): void
    {
        $this->assertTrue($this->service->isAllowed('192.168.1.1', [], []));
        $this->assertTrue($this->service->isAllowed('10.0.0.1', [], []));
    }

    #[Test]
    public function allowlist_allows_exact_match(): void
    {
        $this->assertTrue(
            $this->service->isAllowed('192.168.1.1', ['192.168.1.1', '10.0.0.1'], [])
        );
    }

    #[Test]
    public function allowlist_allows_cidr_match(): void
    {
        $this->assertTrue(
            $this->service->isAllowed('192.168.1.50', ['192.168.1.0/24'], [])
        );
    }

    #[Test]
    public function allowlist_denies_non_matching_ip(): void
    {
        $this->assertFalse(
            $this->service->isAllowed('192.168.2.1', ['192.168.1.0/24', '10.0.0.1'], [])
        );
    }

    #[Test]
    public function blocklist_takes_precedence_over_allowlist(): void
    {
        $this->assertFalse(
            $this->service->isAllowed('192.168.1.1', ['192.168.1.0/24'], ['192.168.1.1'])
        );
    }

    #[Test]
    public function is_valid_ip_accepts_valid_ipv4(): void
    {
        $this->assertTrue($this->service->isValidIp('192.168.1.1'));
        $this->assertTrue($this->service->isValidIp('10.0.0.1'));
        $this->assertTrue($this->service->isValidIp('255.255.255.255'));
    }

    #[Test]
    public function is_valid_ip_accepts_valid_ipv6(): void
    {
        $this->assertTrue($this->service->isValidIp('2001:0db8:85a3:0000:0000:8a2e:0370:7334'));
        $this->assertTrue($this->service->isValidIp('::1'));
    }

    #[Test]
    public function is_valid_ip_rejects_invalid_strings(): void
    {
        $this->assertFalse($this->service->isValidIp('not.an.ip'));
        $this->assertFalse($this->service->isValidIp('localhost'));
        $this->assertFalse($this->service->isValidIp(''));
    }

    #[Test]
    public function ip_in_cidr_returns_true_for_ips_inside_range(): void
    {
        $this->assertTrue($this->service->ipInCidr('192.168.1.1', '192.168.1.0/24'));
        $this->assertTrue($this->service->ipInCidr('192.168.1.254', '192.168.1.0/24'));
        $this->assertTrue($this->service->ipInCidr('10.0.0.5', '10.0.0.0/8'));
    }

    #[Test]
    public function ip_in_cidr_returns_false_for_ips_outside_range(): void
    {
        $this->assertFalse($this->service->ipInCidr('192.168.2.1', '192.168.1.0/24'));
        $this->assertFalse($this->service->ipInCidr('10.1.0.1', '10.0.0.0/16'));
    }

    #[Test]
    public function ip_in_cidr_returns_false_for_invalid_cidr(): void
    {
        $this->assertFalse($this->service->ipInCidr('192.168.1.1', '192.168.0.0/33'));
        $this->assertFalse($this->service->ipInCidr('192.168.1.1', '192.168.0.0/abc'));
        $this->assertFalse($this->service->ipInCidr('192.168.1.1', 'invalid'));
    }

    #[Test]
    public function ip_in_cidr_returns_false_for_ipv6_addresses(): void
    {
        $this->assertFalse($this->service->ipInCidr('2001:db8::1', '192.168.1.0/24'));
    }
}
