<?php

namespace App\Services\System;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheInvalidationService
{
    public function forgetPosition(int|string|null $branchId, string $currencyCode): void
    {
        Cache::forget(CacheKeys::positionAvailable($branchId, $currencyCode));
    }

    public function forgetCustomer(int|string $customerId): void
    {
        Cache::forget(CacheKeys::customer($customerId));
    }

    public function forgetRate(string $currencyCode, ?int $branchId = null): void
    {
        Cache::forget(CacheKeys::rate($currencyCode, $branchId));
    }

    /**
     * Forget every rate cache entry that can resolve to the given currencies:
     * the company-wide key plus each branch-scoped key.
     *
     * Used by writers whose change alters what a branch reader resolves to
     * (a company-wide card is the fallback for branches without their own),
     * so no reader serves a stale rate until the TTL expires.
     *
     * @param  list<string>  $currencies
     * @param  list<int>  $branchIds
     */
    public function forgetRateScopes(array $currencies, array $branchIds = []): void
    {
        foreach ($currencies as $currency) {
            $this->forgetRate($currency);

            foreach ($branchIds as $branchId) {
                $this->forgetRate($currency, $branchId);
            }
        }
    }

    public function forgetExchangeRates(?int $branchId = null): void
    {
        Cache::forget(CacheKeys::exchangeRates($branchId));
        // The transaction form caches the full rate table under a separate key;
        // forgetting only the branch key left tellers reading stale rates until TTL.
        Cache::forget(CacheKeys::ExchangeRates->value);
    }

    public function forgetWizardSession(string $sessionId): void
    {
        Cache::forget(CacheKeys::wizardSession($sessionId));
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Invalidate all cache entries with the given tag.
     *
     * Tagged flush requires a taggable store (Redis/Memcached/Database/Array).
     * On stores without tagging support (e.g. the file driver) Cache::tags()
     * throws BadMethodCallException, which would break the calling business
     * operation (transaction creation/approval). Fall back to a full flush so
     * stale data can never block a write path.
     */
    public function invalidate(string $tag): void
    {
        if (! $this->supportsTags()) {
            // Tagged flush is impossible here; a full flush is the only way
            // to guarantee no stale entries survive the invalidation.
            Cache::store()->flush();
            Log::warning("Cache store does not support tags; performed a FULL flush of the default cache store instead of tag '{$tag}' invalidation. Consider using Redis/Memcached/Database for production.");

            return;
        }

        Cache::tags([$tag])->flush();
    }

    /**
     * Whether the active cache store supports tags.
     */
    public function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
