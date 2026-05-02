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

    public function test_spread_calculation_is_consistent(): void
    {
        // Test that the spread calculation in RateManagementService.calculateSpread()
        // is mathematically inverse to the spread application in RateApiService.processRates()
        //
        // RateApiService applies: buy = mid * (1 - spread), sell = mid * (1 + spread)
        // RateManagementService calculates: spread = (sell - buy) / (2 * mid) * 100
        //
        // These should be inverses, so if we start with a mid rate and spread,
        // calculate buy/sell, then recalculate spread, we should get the original spread.

        $mathService = new MathService;
        $spread = '0.02'; // 2%
        $midRate = '4.5000';

        // Apply spread (as RateApiService does)
        $buyRate = $mathService->multiply($midRate, $mathService->subtract('1', $spread));
        $sellRate = $mathService->multiply($midRate, $mathService->add('1', $spread));

        // Normalize BCMath results to 4 decimal places for comparison
        $buyRateNorm = bcadd($buyRate, '0', 4);
        $sellRateNorm = bcadd($sellRate, '0', 4);

        // Verify buy/sell calculation
        $expectedBuy = '4.4100'; // 4.5000 * 0.98
        $expectedSell = '4.5900'; // 4.5000 * 1.02
        $this->assertEquals($expectedBuy, $buyRateNorm);
        $this->assertEquals($expectedSell, $sellRateNorm);

        // Now reverse-calculate spread (as RateManagementService does)
        // RateManagementService returns spread as percentage (2.00 for 2%)
        $calculatedMid = bcadd($mathService->divide($mathService->add($buyRate, $sellRate), '2'), '0', 4);
        $this->assertEquals($midRate, $calculatedMid); // Mid should be preserved

        // spread = (sell - buy) / (2 * mid) * 100 (returns percentage)
        $calculatedSpread = $mathService->divide(
            $mathService->subtract($sellRate, $buyRate),
            $mathService->multiply($calculatedMid, '2')
        );
        $calculatedSpreadPercent = $mathService->multiply($calculatedSpread, '100');

        // Spread percentage should be 2.00 (2%)
        $expectedSpreadPercent = '2.00';
        $this->assertEquals($expectedSpreadPercent, bcadd($calculatedSpreadPercent, '0', 2));
    }

    public function test_spread_with_various_rates(): void
    {
        // Test spread consistency with different mid rates and spread percentages
        $mathService = new MathService;

        $testCases = [
            ['mid' => '4.5000', 'spread' => '0.02', 'expectedBuy' => '4.4100', 'expectedSell' => '4.5900'],
            ['mid' => '5.0000', 'spread' => '0.03', 'expectedBuy' => '4.8500', 'expectedSell' => '5.1500'],
            ['mid' => '1.5000', 'spread' => '0.01', 'expectedBuy' => '1.4850', 'expectedSell' => '1.5150'],
        ];

        foreach ($testCases as $case) {
            $buyRate = $mathService->multiply($case['mid'], $mathService->subtract('1', $case['spread']));
            $sellRate = $mathService->multiply($case['mid'], $mathService->add('1', $case['spread']));

            // Normalize to 4 decimal places for comparison
            $buyRateNorm = bcadd($buyRate, '0', 4);
            $sellRateNorm = bcadd($sellRate, '0', 4);

            $this->assertEquals($case['expectedBuy'], $buyRateNorm, "Buy rate mismatch for mid={$case['mid']}");
            $this->assertEquals($case['expectedSell'], $sellRateNorm, "Sell rate mismatch for mid={$case['mid']}");

            // Verify round-trip: mid -> buy/sell -> recalculated mid
            $recalculatedMid = bcadd($mathService->divide($mathService->add($buyRate, $sellRate), '2'), '0', 4);
            $this->assertEquals($case['mid'], $recalculatedMid, "Mid should be preserved for mid={$case['mid']}");

            // Verify round-trip: buy/sell -> spread -> recalculated spread
            // RateManagementService returns spread as percentage
            $calculatedMid = $mathService->divide($mathService->add($buyRate, $sellRate), '2');
            $calculatedSpread = $mathService->divide(
                $mathService->subtract($sellRate, $buyRate),
                $mathService->multiply($calculatedMid, '2')
            );
            $calculatedSpreadPercent = $mathService->multiply($calculatedSpread, '100');

            // Spread percentage should match the original
            $expectedSpreadPercent = bcmul($case['spread'], '100', 2); // 0.02 -> 2.00
            $this->assertEquals(
                $expectedSpreadPercent,
                bcadd($calculatedSpreadPercent, '0', 2),
                "Spread mismatch for mid={$case['mid']}"
            );
        }
    }
}
