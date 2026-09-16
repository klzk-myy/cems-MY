<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateOverrideEffectiveDateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function future_dated_override_is_not_served_until_effective(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Carbon::setTestNow(now());

        $service = app(RateManagementService::class);
        $result = $service->overrideRate(
            'USD',
            '5.0000',
            '5.1000',
            $manager,
            'Scheduled increase',
            null,
            now()->addDay()->toDateString()
        );

        $this->assertTrue($result->success);

        // Before the effective date the scheduled override is ignored:
        // lookups must not serve it early.
        $rates = $service->getCurrentRates();
        $this->assertNull($rates->firstWhere('currency_code', 'USD'));

        Cache::forget('rate:USD');
        $this->assertNull($service->getRateForCurrency('USD'));

        // Once the effective date passes, the override applies.
        Carbon::setTestNow(now()->addDays(2));
        Cache::forget('rate:USD');

        $rates = $service->getCurrentRates();
        $this->assertEquals('5.00000000', $rates->firstWhere('currency_code', 'USD')->rate_buy);
        $this->assertEquals('5.10000000', $rates->firstWhere('currency_code', 'USD')->rate_sell);

        $servedRate = $service->getRateForCurrency('USD');
        $this->assertInstanceOf(ExchangeRate::class, $servedRate);
        $this->assertEquals('5.00000000', $servedRate->rate_buy);

        Carbon::setTestNow();
    }

    #[Test]
    public function override_without_effective_date_applies_immediately(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'rate_buy' => '5.0000',
            'rate_sell' => '5.1000',
            'fetched_at' => now(),
        ]);

        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $service = app(RateManagementService::class);
        $result = $service->overrideRate('EUR', '5.1000', '5.2000', $manager);

        $this->assertTrue($result->success);

        $rate = $service->getCurrentRates()->firstWhere('currency_code', 'EUR');
        $this->assertEquals('5.10000000', $rate->rate_buy);
        // Immediate overrides are effective right away.
        $this->assertTrue($rate->effective_date->lessThanOrEqualTo(now()));
    }
}
