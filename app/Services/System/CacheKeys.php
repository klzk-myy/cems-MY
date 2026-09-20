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

    /** Terminal setup-complete flag probed by HomeController. */
    case SetupComplete = 'setup:complete';

    /** Distinct customer nationalities for the index filter dropdown. */
    case CustomerNationalities = 'customer_nationalities';

    /** Compliance flag status counts for the dashboard. */
    case ComplianceFlagStatusCounts = 'compliance_flag_status_counts';

    /** Cache tag used for account-mapping resolutions. */
    case AccountMappingsTag = 'account-mappings';

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

    public static function rate(string $currencyCode, ?int $branchId = null): string
    {
        return 'rate:'.$currencyCode.($branchId !== null ? ':branch:'.$branchId : '');
    }

    public static function exchangeRates(?int $branchId = null): string
    {
        return $branchId ? "exchange_rates_branch_{$branchId}" : 'exchange_rates';
    }

    /**
     * Cached distinct-value list for a SystemLog column (audit filters).
     */
    public static function auditLogDistinct(string $column): string
    {
        return "audit_log_distinct_{$column}s";
    }

    /**
     * Resolved chart account for an account_mappings key.
     */
    public static function accountMapping(string $key): string
    {
        return "account_mapping:{$key}";
    }
}
