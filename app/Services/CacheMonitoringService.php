<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CacheMonitoringService
{
    protected const HITS_KEY = 'cache:monitoring:hits';

    protected const MISSES_KEY = 'cache:monitoring:misses';

    protected const LAST_RESET_KEY = 'cache:monitoring:last_reset';

    public function getCacheStats(): array
    {
        return [
            'hit_rate' => $this->calculateHitRate(),
            'memory_usage' => $this->getMemoryUsage(),
            'keys_count' => $this->getKeysCount(),
        ];
    }

    public function calculateHitRate(): float
    {
        $hits = (int) Redis::get(self::HITS_KEY) ?: 0;
        $misses = (int) Redis::get(self::MISSES_KEY) ?: 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0.0;
        }

        return ($hits / $total) * 100;
    }

    public function recordHit(): void
    {
        Redis::incr(self::HITS_KEY);
    }

    public function recordMiss(): void
    {
        Redis::incr(self::MISSES_KEY);
    }

    public function resetCounters(): void
    {
        Redis::set(self::HITS_KEY, 0);
        Redis::set(self::MISSES_KEY, 0);
        Redis::set(self::LAST_RESET_KEY, time());
    }

    protected function getMemoryUsage(): array
    {
        try {
            $info = Redis::info('memory');

            return [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
            ];
        } catch (\Exception $e) {
            return [
                'used_memory' => 0,
                'used_memory_peak' => 0,
                'used_memory_human' => '0B',
            ];
        }
    }

    protected function getKeysCount(): int
    {
        try {
            return Redis::dbsize();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
