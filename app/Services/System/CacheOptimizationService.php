<?php

namespace App\Services\System;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class CacheOptimizationService
{
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
    ];

    /**
     * Remember a value by key with tags and TTL, tracking hits/misses.
     *
     * Falls back to untagged caching when the active store does not support
     * tags (e.g. the file driver), otherwise Cache::tags() would throw a
     * BadMethodCallException on every dashboard read.
     */
    public function remember(string $key, int $ttl, array $tags, \Closure $callback): mixed
    {
        if (Cache::getStore() instanceof TaggableStore) {
            $cache = Cache::tags($tags);
        } else {
            $cache = Cache::store();
        }

        // Atomic check-and-fill: a single remember() costs one round-trip on a
        // hit, versus two for has()+get(), and cannot race between them.
        $wasHit = true;
        $value = $cache->remember($key, now()->addSeconds($ttl), function () use ($callback, &$wasHit) {
            $wasHit = false;

            return $callback();
        });

        $this->stats[$wasHit ? 'hits' : 'misses']++;

        return $value;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function putStats(\DateTimeInterface $ttl): void
    {
        Cache::put(CacheKeys::DashboardCacheStats->value, $this->stats, $ttl);
    }

    public function resetStats(): void
    {
        $this->stats = ['hits' => 0, 'misses' => 0];
    }
}
