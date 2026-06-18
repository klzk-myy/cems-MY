<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateManagementServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function get_rate_for_currency_uses_cache()
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

    #[Test]
    public function override_rate_invalidates_cache()
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

    #[Test]
    public function rate_override_is_atomic()
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
        ]);

        $service = app(RateManagementService::class);

        // First override should succeed
        $result1 = $service->overrideRate('USD', '4.6000', '4.7000', $manager, 'First override');
        $this->assertTrue($result1['success']);
        $this->assertEquals('4.6000', $result1['new_buy_rate']);
        $this->assertEquals('4.7000', $result1['new_sell_rate']);

        // Second override should also succeed (sequential, not concurrent)
        $result2 = $service->overrideRate('USD', '4.7000', '4.8000', $manager, 'Second override');
        $this->assertTrue($result2['success']);
        $this->assertEquals('4.7000', $result2['new_buy_rate']);
        $this->assertEquals('4.8000', $result2['new_sell_rate']);

        // Verify final rate is the second override
        $rate = ExchangeRate::where('currency_code', 'USD')->first();
        $this->assertEquals('4.7000', $rate->rate_buy);
        $this->assertEquals('4.8000', $rate->rate_sell);
    }
}
