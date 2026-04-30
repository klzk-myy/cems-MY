<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheOptimizationService
{
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
    ];

    /**
     * Remember a value by key with tags and TTL, tracking hits/misses.
     */
    public function remember(string $key, int $ttl, array $tags, \Closure $callback)
    {
        $cache = Cache::tags($tags);
        if ($cache->has($key)) {
            $this->stats['hits']++;

            return $cache->get($key);
        }

        $this->stats['misses']++;
        $value = $callback();
        $cache->put($key, $value, now()->addSeconds($ttl));

        return $value;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function putStats(\DateTimeInterface $ttl): void
    {
        Cache::put('dashboard_cache_stats', $this->stats, $ttl);
    }

    public function resetStats(): void
    {
        $this->stats = ['hits' => 0, 'misses' => 0];
    }
}
