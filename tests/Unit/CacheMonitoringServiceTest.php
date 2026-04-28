<?php

namespace Tests\Unit;

use App\Services\CacheMonitoringService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheMonitoringServiceTest extends TestCase
{
    public function test_get_cache_stats_returns_structure()
    {
        $service = app(CacheMonitoringService::class);
        $stats = $service->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertArrayHasKey('keys_count', $stats);
    }

    public function test_calculate_hit_rate_returns_float()
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
