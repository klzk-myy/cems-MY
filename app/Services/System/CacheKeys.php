<?php

namespace App\Services\System;

/**
 * Centralized cache key and tag constants.
 *
 * Using this enum prevents hardcoded cache key strings from being scattered
 * across the codebase and makes invalidation/surveying easier.
 */
enum CacheKeys: string
{
    case ExchangeRates = 'exchange_rates_for_transactions';
    case DashboardCacheStats = 'dashboard_cache_stats';
    case CurrentResponseTimeMs = 'current_response_time_ms';
    case CurrentCacheHitRate = 'current_cache_hit_rate';

    /** Cache tag used for dashboard-related data. */
    case DashboardTag = 'dashboard';

    /** Cache tag used for ledger data. */
    case LedgerTag = 'ledger';

    /** Cache tag used for trial-balance data. */
    case TrialBalanceTag = 'trial-balance';

    /** Cache tag used for account balance data. */
    case BalancesTag = 'balances';

    /**
     * Build a wizard session cache key.
     */
    public static function wizardSession(string $sessionId): string
    {
        return "wizard:{$sessionId}";
    }

    /**
     * Build an available-position cache key.
     */
    public static function positionAvailable(int|string|null $branchId, string $currencyCode): string
    {
        return 'position:'.($branchId ?? 'none').":{$currencyCode}:available";
    }

    /**
     * Build a customer cache key.
     */
    public static function customer(int|string $customerId): string
    {
        return "customer:{$customerId}";
    }
}
