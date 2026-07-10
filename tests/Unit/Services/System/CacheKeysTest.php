<?php

namespace Tests\Unit\Services\System;

use App\Services\System\CacheKeys;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheKeysTest extends TestCase
{
    #[Test]
    public function cache_keys_enum_has_expected_cases(): void
    {
        $this->assertSame('exchange_rates_for_transactions', CacheKeys::ExchangeRates->value);
        $this->assertSame('dashboard_cache_stats', CacheKeys::DashboardCacheStats->value);
        $this->assertSame('current_response_time_ms', CacheKeys::CurrentResponseTimeMs->value);
        $this->assertSame('current_cache_hit_rate', CacheKeys::CurrentCacheHitRate->value);
        $this->assertSame('dashboard', CacheKeys::DashboardTag->value);
        $this->assertSame('ledger', CacheKeys::LedgerTag->value);
        $this->assertSame('trial-balance', CacheKeys::TrialBalanceTag->value);
        $this->assertSame('balances', CacheKeys::BalancesTag->value);
    }

    #[Test]
    public function dynamic_key_helpers_return_expected_strings(): void
    {
        $this->assertSame('wizard:session-123', CacheKeys::wizardSession('session-123'));
        $this->assertSame('position:1:USD:available', CacheKeys::positionAvailable(1, 'USD'));
        $this->assertSame('customer:42', CacheKeys::customer(42));
    }
}
