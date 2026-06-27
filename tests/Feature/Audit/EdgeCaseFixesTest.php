<?php

namespace Tests\Feature\Audit;

use App\Services\System\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCaseFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_cidr_mask_returns_false(): void
    {
        $service = app(RateLimitService::class);
        $this->assertFalse($service->ipInCidr('192.168.1.1', '192.168.0.0/33'));
        $this->assertFalse($service->ipInCidr('192.168.1.1', '192.168.0.0/abc'));
    }
}
