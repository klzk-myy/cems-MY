<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidRateException;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Transaction\RateManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateOverrideSpreadLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function createManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::Manager,
        ]);
    }

    #[Test]
    public function override_with_spread_above_maximum_is_rejected(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $manager = $this->createManager();

        // Spread = 0.6 / 8.6 = 6.98% > max 5%
        $this->expectException(InvalidRateException::class);
        $this->expectExceptionMessage('exceeds the maximum allowed spread');

        app(RateManagementService::class)->overrideRate('USD', '4.0000', '4.6000', $manager);
    }

    #[Test]
    public function override_with_spread_below_minimum_is_rejected(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $manager = $this->createManager();

        // Spread = 0.001 / 9.999 = 0.01% < min 0.5%
        $this->expectException(InvalidRateException::class);
        $this->expectExceptionMessage('below the minimum required spread');

        app(RateManagementService::class)->overrideRate('USD', '4.9990', '5.0000', $manager);
    }

    #[Test]
    public function override_within_spread_limits_is_accepted(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $manager = $this->createManager();

        // Spread = 0.1 / 9.3 = ~1.08%, inside [0.5%, 5%]
        $result = app(RateManagementService::class)
            ->overrideRate('USD', '4.6000', '4.7000', $manager);

        $this->assertTrue($result->success);

        $rate = ExchangeRate::where('currency_code', 'USD')->first();
        $this->assertEquals('4.6000', $rate->rate_buy);
        $this->assertEquals('4.7000', $rate->rate_sell);
    }
}
