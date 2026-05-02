<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Services\MathService;
use App\Services\RateApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RateApiService $service;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->service = new RateApiService($this->mathService);
    }

    public function test_rate_deviation_uses_mid_rate_for_mid_type(): void
    {
        // Arrange: Create an exchange rate with known buy and sell rates
        // Using buy = 4.5000 and sell = 4.6000, mid should be (4.5000 + 4.6000) / 2 = 4.5500
        $exchangeRate = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Act: Get the mid rate
        $midRate = $this->service->getCurrentRate('USD', 'mid');

        // Assert: Mid rate should be the average of buy and sell
        $expectedMid = '4.5500';
        $this->assertEquals($expectedMid, $midRate);

        // Also verify buy and sell rates are returned correctly
        $this->assertEquals('4.5000', $this->service->getCurrentRate('USD', 'buy'));
        $this->assertEquals('4.6000', $this->service->getCurrentRate('USD', 'sell'));
    }

    public function test_mid_rate_calculation_with_odd_values(): void
    {
        // Arrange: Create an exchange rate where (buy + sell) / 2 results in a .5 decimal
        $exchangeRate = ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'rate_buy' => '4.3000',
            'rate_sell' => '4.5000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Act: Get the mid rate
        $midRate = $this->service->getCurrentRate('EUR', 'mid');

        // Assert: Mid rate should be (4.3000 + 4.5000) / 2 = 4.4000
        $this->assertEquals('4.4000', $midRate);
    }

    public function test_get_current_rate_returns_null_for_unknown_currency(): void
    {
        // Act & Assert
        $this->assertNull($this->service->getCurrentRate('XYZ'));
    }

    public function test_validate_rate_deviation_with_mid_type(): void
    {
        // Arrange: Create an exchange rate with known buy and sell rates
        $exchangeRate = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // The mid rate is 4.5500
        // A submitted rate of 4.5520 has a deviation of 0.0020 from mid
        // Deviation percent = (0.0020 / 4.5500) * 100 = 0.044% < 0.05% threshold (valid)

        // Act: Validate a rate that is within the 5% threshold
        $result = $this->service->validateRateDeviation('4.5520', 'USD', 'mid');

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertEquals('4.5500', $result['market_rate']);
    }
}
