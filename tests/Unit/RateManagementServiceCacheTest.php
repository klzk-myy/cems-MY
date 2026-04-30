<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateManagementServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_rate_for_currency_uses_cache()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(ExchangeRate::first());

        $service = app(RateManagementService::class);
        $rate = $service->getRateForCurrency('USD');

        $this->assertInstanceOf(ExchangeRate::class, $rate);
        $this->assertEquals('4.5000', $rate->rate_buy);
    }

    public function test_override_rate_invalidates_cache()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Expect cache forget for key 'rate:USD'
        Cache::shouldReceive('forget')
            ->once()
            ->with('rate:USD');

        $service = app(RateManagementService::class);
        // Create a manager user to authorize override
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        $service->overrideRate('USD', '4.6000', '4.7000', $manager);
    }
}
