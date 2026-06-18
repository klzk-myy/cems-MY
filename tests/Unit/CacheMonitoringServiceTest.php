<?php

namespace Tests\Unit;

use App\Services\CacheMonitoringService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheMonitoringServiceTest extends TestCase
{
    #[Test]
    public function get_cache_stats_returns_structure()
    {
        $service = app(CacheMonitoringService::class);
        $stats = $service->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertArrayHasKey('total_keys', $stats);
    }

    #[Test]
    public function calculate_hit_rate_returns_float()
    {
        Cache::put('test_key', 'test_value');
        Cache::get('test_key');

        $service = app(CacheMonitoringService::class);
        $hitRate = $service->calculateHitRate();

        $this->assertIsFloat($hitRate);
        $this->assertGreaterThanOrEqual(0, $hitRate);
        $this->assertLessThanOrEqual(100, $hitRate);
    }
}
