<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Services\Security\IpValidationService;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing rate limiting and IP blocking.
 *
 * Provides centralized rate limit logic, IP-based blocking,
 * and rate limit monitoring for BNM compliance.
 */
class RateLimitService
{
    /** @var string Cache prefix for IP blocking */
    private const IP_BLOCK_PREFIX = 'ip_block:';

    /** @var string Cache prefix for failed login attempts */
    private const FAILED_ATTEMPTS_PREFIX = 'ip_failed:';

    /** @var string Cache prefix for rate limit hits */
    private const RATE_LIMIT_HITS_PREFIX = 'rate_limit_hits:';

    /** @var string Cache prefix for burst tracking */
    private const BURST_PREFIX = 'burst:';

    /** @var string Cache key for blocked IPs index (list of all blocked IPs) */
    private const BLOCKED_IPS_INDEX = 'ip_block:all';

    /**
     * Check if an IP address is currently blocked.
     */
    public function isIpBlocked(string $ip): bool
    {
        if (! config('security.ip_blocking.enabled', true)) {
            return false;
        }

        // Check whitelist (supports both exact IP and CIDR notation)
        $whitelist = config('security.ip_blocking.whitelist', []);
        if ($this->isIpWhitelisted($ip, $whitelist)) {
            return false;
        }

        // Check if IP is in block cache
        $cacheKey = self::IP_BLOCK_PREFIX.$ip;

        return Cache::store(config('ratelimit.store'))->has($cacheKey);
    }

    /**
     * Check if an IP is in the whitelist.
     * Supports both exact IP addresses and CIDR notation (e.g., 192.168.1.0/24).
     */
    private function isIpWhitelisted(string $ip, array $whitelist): bool
    {
        if ($whitelist === []) {
            return false;
        }

        return app(IpValidationService::class)->isAllowed($ip, $whitelist, []);
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    public function ipInCidr(string $ip, string $cidr): bool
    {
        return app(IpValidationService::class)->ipInCidr($ip, $cidr);
    }

    /**
     * Block an IP address for a specified duration.
     */
    public function blockIp(string $ip, ?int $durationMinutes = null): void
    {
        if (! config('security.ip_blocking.enabled', true)) {
            return;
        }

        $duration = $durationMinutes ?? config('security.ip_blocking.block_duration_minutes', 60);
        $maxDuration = config('security.ip_blocking.max_block_duration_minutes', 1440);

        // Check if IP has been blocked before - increase duration for repeat offenders
        $blockCount = $this->getIpBlockCount($ip);
        $actualDuration = min($duration * (2 ** $blockCount), $maxDuration);

        $cacheKey = self::IP_BLOCK_PREFIX.$ip;
        $cache = Cache::store(config('ratelimit.store'));
        $cache->put($cacheKey, [
            'blocked_at' => now()->toDateTimeString(),
            'duration_minutes' => $actualDuration,
            'block_count' => $blockCount + 1,
        ], now()->addMinutes($actualDuration));

        // Maintain an index of all blocked IPs for getBlockedIps().
        // Use a Redis set for atomic add/remove when Redis is the store;
        // fall back to a cached array for non-Redis drivers (e.g. array cache in tests).
        $store = $cache->getStore();
        if ($store instanceof RedisStore) {
            $store->connection()->sadd(self::BLOCKED_IPS_INDEX, $ip);
            $store->connection()->expire(self::BLOCKED_IPS_INDEX, $maxDuration * 60);
        } else {
            $blockedList = $cache->get(self::BLOCKED_IPS_INDEX, []);
            if (! in_array($ip, $blockedList, true)) {
                $blockedList[] = $ip;
                $cache->put(self::BLOCKED_IPS_INDEX, $blockedList, now()->addMinutes($maxDuration));
            }
        }

        Log::warning('IP address blocked due to security policy', [
            'ip' => $ip,
            'duration_minutes' => $actualDuration,
            'block_count' => $blockCount + 1,
            'reason' => 'excessive_failed_attempts',
        ]);
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(string $ip): bool
    {
        $cacheKey = self::IP_BLOCK_PREFIX.$ip;
        $cache = Cache::store(config('ratelimit.store'));

        if ($cache->has($cacheKey)) {
            $cache->forget($cacheKey);

            // Remove IP from the blocked IPs index atomically when Redis is available.
            $store = $cache->getStore();
            if ($store instanceof RedisStore) {
                $store->connection()->srem(self::BLOCKED_IPS_INDEX, $ip);
            } else {
                $blockedList = $cache->get(self::BLOCKED_IPS_INDEX, []);
                $blockedList = array_values(array_filter($blockedList, fn ($bip) => $bip !== $ip));
                if (! empty($blockedList)) {
                    $cache->put(self::BLOCKED_IPS_INDEX, $blockedList, now()->addDay());
                } else {
                    $cache->forget(self::BLOCKED_IPS_INDEX);
                }
            }

            Log::info('IP address unblocked', ['ip' => $ip]);

            return true;
        }

        return false;
    }

    /**
     * Record a failed login attempt for an IP.
     */
    public function recordFailedAttempt(string $ip): void
    {
        if (! config('security.ip_blocking.enabled', true)) {
            return;
        }

        $cacheKey = self::FAILED_ATTEMPTS_PREFIX.$ip;
        $window = config('security.ip_blocking.time_window_minutes', 5);
        $threshold = config('security.ip_blocking.failed_attempts_threshold', 10);

        // Seed counter atomically and then increment to avoid a TTL-reset race
        $store = Cache::store(config('ratelimit.store'));
        $store->add($cacheKey, 0, now()->addMinutes($window));
        $attempts = $store->increment($cacheKey);

        // Auto-block if threshold exceeded
        if ($attempts >= $threshold) {
            $this->blockIp($ip);
        }
    }

    /**
     * Get the number of failed attempts for an IP.
     */
    public function getFailedAttempts(string $ip): int
    {
        $cacheKey = self::FAILED_ATTEMPTS_PREFIX.$ip;

        return (int) Cache::store(config('ratelimit.store'))->get($cacheKey, 0);
    }

    /**
     * Clear failed attempts for an IP.
     */
    public function clearFailedAttempts(string $ip): void
    {
        $cacheKey = self::FAILED_ATTEMPTS_PREFIX.$ip;
        Cache::store(config('ratelimit.store'))->forget($cacheKey);
    }

    /**
     * Get block information for an IP.
     */
    public function getIpBlockInfo(string $ip): ?array
    {
        $cacheKey = self::IP_BLOCK_PREFIX.$ip;
        $data = Cache::store(config('ratelimit.store'))->get($cacheKey);

        if (! $data) {
            return null;
        }

        return [
            'ip' => $ip,
            'blocked_at' => $data['blocked_at'],
            'duration_minutes' => $data['duration_minutes'],
            'expires_at' => now()->parse($data['blocked_at'])->addMinutes($data['duration_minutes'])->toDateTimeString(),
            'block_count' => $data['block_count'] ?? 1,
        ];
    }

    /**
     * Get all blocked IPs.
     */
    public function getBlockedIps(): array
    {
        $cache = Cache::store(config('ratelimit.store'));
        $store = $cache->getStore();

        if ($store instanceof RedisStore) {
            $blockedIps = $store->connection()->smembers(self::BLOCKED_IPS_INDEX);
        } else {
            $blockedIps = $cache->get(self::BLOCKED_IPS_INDEX, []);
        }

        $blocked = [];

        foreach ($blockedIps as $ip) {
            $info = $this->getIpBlockInfo($ip);
            if ($info) {
                $blocked[] = $info;
            }
        }

        return $blocked;
    }

    /**
     * Log a rate limit hit for monitoring.
     */
    public function logRateLimitHit(Request $request, string $limiterName): void
    {
        if (! config('security.rate_limit_monitoring.enabled', true)) {
            return;
        }

        $ip = $request->ip();
        $userId = $request->user()?->id;

        Log::warning('Rate limit exceeded', [
            'ip' => $ip,
            'user_id' => $userId,
            'limiter' => $limiterName,
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        // Store hit for alert analysis
        $cacheKey = self::RATE_LIMIT_HITS_PREFIX.$ip;
        $hits = Cache::store(config('ratelimit.store'))->get($cacheKey, []);
        $hits[] = [
            'timestamp' => now()->toDateTimeString(),
            'limiter' => $limiterName,
            'user_id' => $userId,
        ];

        // Keep only hits within the alert window
        $window = config('security.rate_limit_monitoring.alert_window_minutes', 10);
        $cutoff = now()->subMinutes($window);
        $hits = array_filter($hits, function ($hit) use ($cutoff) {
            return now()->parse($hit['timestamp'])->greaterThanOrEqualTo($cutoff);
        });

        Cache::store(config('ratelimit.store'))->put($cacheKey, $hits, now()->addMinutes($window));

        // Check if alert threshold is exceeded
        $this->checkAlertThreshold($ip, $hits);
    }

    /**
     * Check if alert threshold is exceeded and trigger alert.
     */
    private function checkAlertThreshold(string $ip, array $hits): void
    {
        $threshold = config('security.rate_limit_monitoring.alert_threshold', 3);

        if (count($hits) >= $threshold) {
            Log::alert('Rate limit alert threshold exceeded', [
                'ip' => $ip,
                'hit_count' => count($hits),
                'threshold' => $threshold,
                'hits' => $hits,
            ]);

            // Could also dispatch an alert event/notification here
        }
    }

    /**
     * Get rate limit statistics for an IP.
     */
    public function getRateLimitStats(string $ip): array
    {
        $cacheKey = self::RATE_LIMIT_HITS_PREFIX.$ip;
        $hits = Cache::store(config('ratelimit.store'))->get($cacheKey, []);

        return [
            'ip' => $ip,
            'total_hits' => count($hits),
            'is_blocked' => $this->isIpBlocked($ip),
            'failed_attempts' => $this->getFailedAttempts($ip),
            'recent_hits' => $hits,
        ];
    }

    /**
     * Get overall rate limiting statistics.
     */
    public function getOverallStats(): array
    {
        $blockedIps = $this->getBlockedIps();

        return [
            'blocked_ips_count' => count($blockedIps),
            'blocked_ips' => $blockedIps,
        ];
    }

    /**
     * Check burst allowance for a request.
     * Returns true if request is within burst allowance.
     */
    public function checkBurst(Request $request, string $limiterName, int $burstAllowance): bool
    {
        $ip = $request->ip();
        $userId = $request->user()?->id;
        $key = $userId ? "{$limiterName}:user:{$userId}" : "{$limiterName}:ip:{$ip}";
        $cacheKey = self::BURST_PREFIX.$key;

        $burst = Cache::store(config('ratelimit.store'))->get($cacheKey, []);
        $now = now();

        // Remove old entries outside the burst window (1 second)
        $burst = array_filter($burst, function ($timestamp) use ($now) {
            return $now->diffInSeconds($timestamp) < 1;
        });

        // Check if burst allowance is exceeded
        if (count($burst) >= $burstAllowance) {
            return false;
        }

        // Add current request to burst
        $burst[] = $now;
        Cache::store(config('ratelimit.store'))->put($cacheKey, $burst, now()->addSeconds(1));

        return true;
    }

    /**
     * Get the rate limit key for a request.
     */
    public function getRateLimitKey(Request $request, string $limiterName): string
    {
        $userId = $request->user()?->id;

        return $userId ? "{$limiterName}:user:{$userId}" : "{$limiterName}:ip:{$request->ip()}";
    }

    /**
     * Get IP block count (number of times IP has been blocked).
     */
    private function getIpBlockCount(string $ip): int
    {
        $info = $this->getIpBlockInfo($ip);

        return $info ? ($info['block_count'] - 1) : 0;
    }
}
