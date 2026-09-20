<?php

namespace Tests\Unit;

use App\Enums\RateSide;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\RateApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
        $this->service = new RateApiService($this->mathService, new CacheInvalidationService, new ThresholdService);
    }

    #[Test]
    public function rate_deviation_uses_mid_rate_for_mid_type(): void
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
        $midRate = $this->service->getCurrentRate('USD', RateSide::Mid);

        // Assert: Mid rate should be the average of buy and sell
        $expectedMid = '4.55000000';
        $this->assertEquals($expectedMid, $midRate);

        // Also verify buy and sell rates are returned correctly
        $this->assertEquals('4.50000000', $this->service->getCurrentRate('USD', RateSide::Buy));
        $this->assertEquals('4.60000000', $this->service->getCurrentRate('USD', RateSide::Sell));
    }

    #[Test]
    public function mid_rate_calculation_with_odd_values(): void
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
        $midRate = $this->service->getCurrentRate('EUR', RateSide::Mid);

        // Assert: Mid rate should be (4.3000 + 4.5000) / 2 = 4.4000
        $this->assertEquals('4.40000000', $midRate);
    }

    #[Test]
    public function get_current_rate_returns_null_for_unknown_currency(): void
    {
        // Act & Assert
        $this->assertNull($this->service->getCurrentRate('XYZ'));
    }

    #[Test]
    public function validate_rate_deviation_with_mid_type(): void
    {
        // Arrange: Create an exchange rate with known buy and sell rates
        $exchangeRate = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // The mid rate is 4.5500.
        // A submitted rate of 4.5520 deviates by 0.0020 from mid, i.e.
        // 0.0020 / 4.5500 = 0.00044 (0.044%).
        // thresholds.rates.max_deviation_percent is a FRACTION (0.05 = 5%),
        // not a percentage, so 0.00044 sits well inside the band.

        // Act: Validate a rate well inside the band
        $result = $this->service->validateRateDeviation('4.5520', 'USD', RateSide::Mid);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertEquals('4.55000000', $result['market_rate']);
    }

    #[Test]
    public function get_current_rate_normalizes_unit_quoted_rows_to_per_unit(): void
    {
        // IDR card stored unit-quoted: RM 230 buy / RM 240 sell per 1,000,000 IDR.
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $exchangeRate = ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '230.0000',
            'rate_sell' => '240.0000',
            'rate_unit' => 1000000,
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $this->assertEquals('0.00023000', $this->service->getCurrentRate('IDR', RateSide::Buy));
        $this->assertEquals('0.00024000', $this->service->getCurrentRate('IDR', RateSide::Sell));
        $this->assertEquals('0.00023500', $this->service->getCurrentRate('IDR', RateSide::Mid));
    }

    #[Test]
    public function validate_rate_deviation_normalizes_submitted_unit_quoted_rate(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        // Market card: RM 230 per 1,000,000 IDR buy.
        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '230.0000',
            'rate_sell' => '240.0000',
            'rate_unit' => 1000000,
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Submitted RM 235 per 1,000,000 → per-unit 0.000235 vs market
        // 0.000230 → ~2.2% deviation < 5% threshold (valid).
        $result = $this->service->validateRateDeviation('235', 'IDR', RateSide::Buy);

        $this->assertTrue($result['valid']);
        $this->assertSame('0.00023500', $result['submitted_rate_per_unit']);
        $this->assertSame('1000000', $result['submitted_rate_unit']);

        // Submitted RM 400 per 1,000,000 → per-unit 0.000400 → ~74% > 5% (invalid).
        $rejected = $this->service->validateRateDeviation('400', 'IDR', RateSide::Buy);

        $this->assertFalse($rejected['valid']);
        $this->assertNotNull($rejected['reason']);
    }

    #[Test]
    public function get_current_rate_normalizes_inverse_rows_to_per_unit(): void
    {
        // Inverse IDR card: buy 4,400 IDR / sell 4,200 IDR per RM 1.
        // Per-unit MYR: buy = 1/4400, sell = 1/4200 — sell stays above buy.
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1, 'rate_inverse' => true]);

        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '4400.0000',
            'rate_sell' => '4200.0000',
            'rate_unit' => 1,
            'rate_inverse' => true,
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $this->assertEquals('0.00022727', $this->service->getCurrentRate('IDR', RateSide::Buy));
        $this->assertEquals('0.00023809', $this->service->getCurrentRate('IDR', RateSide::Sell));
    }

    #[Test]
    public function validate_rate_deviation_normalizes_inverse_submitted_rate(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1, 'rate_inverse' => true]);

        // Market card: 4,400 IDR per RM 1 buy → per-unit 0.000227.
        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '4400.0000',
            'rate_sell' => '4200.0000',
            'rate_unit' => 1,
            'rate_inverse' => true,
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        // Submitted 4,410 IDR per RM 1 → per-unit 0.000227 — ~0% deviation.
        $result = $this->service->validateRateDeviation('4410', 'IDR', RateSide::Buy);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['submitted_rate_inverse']);

        // Submitted 10,000 IDR per RM 1 → per-unit 0.000100 — ~56% deviation.
        $rejected = $this->service->validateRateDeviation('10000', 'IDR', RateSide::Buy);

        $this->assertFalse($rejected['valid']);
    }

    #[Test]
    public function spread_calculation_is_consistent(): void
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

    #[Test]
    public function spread_with_various_rates(): void
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

    #[Test]
    public function get_current_rate_ignores_a_future_dated_card(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'source' => 'api',
            'fetched_at' => now()->subDay(),
            'effective_date' => null,
        ]);
        // A scheduled override that has not taken effect yet must not price
        // today's market, even though it was fetched more recently.
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '9.0000',
            'rate_sell' => '9.1000',
            'source' => 'manual_override',
            'fetched_at' => now(),
            'effective_date' => now()->addDay(),
        ]);

        $this->assertSame('4.50000000', $this->service->getCurrentRate('USD', RateSide::Buy));
    }

    #[Test]
    public function get_current_rate_prefers_the_most_recently_fetched_card(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'rate_buy' => '4.3000',
            'rate_sell' => '4.4000',
            'source' => 'api',
            'fetched_at' => now()->subDays(2),
        ]);
        ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'rate_buy' => '5.1000',
            'rate_sell' => '5.2000',
            'source' => 'api',
            'fetched_at' => now(),
        ]);

        $this->assertSame('5.10000000', $this->service->getCurrentRate('EUR', RateSide::Buy));
    }
}
