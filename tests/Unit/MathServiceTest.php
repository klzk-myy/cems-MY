<?php

namespace Tests\Unit;

use App\Exceptions\Domain\MathValidationException;
use App\Services\System\MathService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MathService scale decision:
 *
 * Database schema uses decimal(18,4) for monetary amounts (amount_myr,
 * quantity, balance, unrealized_pnl) and decimal(18,6) for rates
 * (rate, avg_cost_rate, last_valuation_rate).
 *
 * MathService default scale=4 was chosen to match the DB monetary precision.
 * Using scale=6 would cause silent rounding when values are stored to DB,
 * e.g., a calculation result of "1234.5678" becomes "1234.5678" at scale=4
 * but "1234.567800" at scale=6 — yet DB would store "1234.5678" anyway,
 * creating inconsistency between in-memory and persisted values.
 *
 * Scale=4 is sufficient for MYR transactions (2 decimal places) while
 * providing headroom for division operations and exchange rate spreads.
 */
class MathServiceTest extends TestCase
{
    protected MathService $math;

    protected function setUp(): void
    {
        parent::setUp();
        // Use scale 2 for currency operations
        $this->math = new MathService(2);
    }

    #[Test]
    public function math_service_scale_matches_database_precision(): void
    {
        // MathService default scale=4 matches DB decimal(18,4) for monetary amounts
        $mathService = new MathService;

        // Verify default scale is 4
        $this->assertEquals(4, $mathService->getScale());

        // Verify monetary amounts are handled at scale=4
        $result = $mathService->multiply('100.1234', '2.5678');
        // 100.1234 * 2.5678 = 257.0968 at scale=4
        $this->assertEquals('257.0968', $result);

        // Verify DB roundtrip: calculation at scale=4 matches DB decimal(18,4) storage
        $amount1 = '1000.1234';
        $amount2 = '500.5678';
        $sum = $mathService->add($amount1, $amount2);
        // Sum is 1500.6912, which fits in decimal(18,4) without truncation
        $this->assertEquals('1500.6912', $sum);
    }

    #[Test]
    public function it_adds_two_numbers_with_precision(): void
    {
        $result = $this->math->add('10.50', '5.25');
        $this->assertEquals('15.75', $result);
    }

    #[Test]
    public function it_subtracts_two_numbers_with_precision(): void
    {
        $result = $this->math->subtract('100.00', '25.50');
        $this->assertEquals('74.50', $result);
    }

    #[Test]
    public function it_multiplies_two_numbers_with_precision(): void
    {
        $result = $this->math->multiply('10.50', '3');
        $this->assertEquals('31.50', $result);
    }

    #[Test]
    public function it_divides_two_numbers_with_precision(): void
    {
        $result = $this->math->divide('100.00', '4');
        $this->assertEquals('25.00', $result);
    }

    #[Test]
    public function it_throws_exception_for_division_by_zero(): void
    {
        $this->expectException(MathValidationException::class);
        $this->expectExceptionMessage('Division by zero');
        $this->math->divide('100.00', '0');
    }

    #[Test]
    public function it_compares_two_decimals_correctly(): void
    {
        $this->assertEquals(0, $this->math->compare('10.50', '10.50'));
        $this->assertEquals(-1, $this->math->compare('10.50', '10.51'));
        $this->assertEquals(1, $this->math->compare('10.50', '10.49'));
    }

    #[Test]
    public function it_handles_large_numbers(): void
    {
        $result = $this->math->add('999999999999.99', '0.01');
        $this->assertEquals('1000000000000.00', $result);
    }

    #[Test]
    public function it_calculates_weighted_average_cost(): void
    {
        // Old: 1000 USD at 4.50 = 4500 MYR
        // New: 500 USD at 4.60 = 2300 MYR
        // Total: 1500 USD at (4500 + 2300) / 1500 = 4.533...
        $result = $this->math->calculateAverageCost('1000', '4.50', '500', '4.60');
        $this->assertEquals('4.53', substr($result, 0, 4));
    }

    #[Test]
    public function it_calculates_revaluation_pnl(): void
    {
        // 1000 USD * (4.60 - 4.50) = 100 MYR gain
        $result = $this->math->calculateRevaluationPnl('1000', '4.50', '4.60');
        $this->assertEquals('100.00', $result);
    }

    #[Test]
    public function it_calculates_transaction_amount(): void
    {
        // 100 USD * 4.50 = 450 MYR
        $result = $this->math->calculateTransactionAmount('100', '4.50');
        $this->assertEquals('450.00', $result);
    }

    #[Test]
    public function it_calculates_revaluation_pnl_honoring_precision_override(): void
    {
        // A rate diff of 0.000002 must survive a scale-4 internal default when
        // the caller requests precision 6 (decimal(18,6) rates).
        $math = new MathService(4);

        $result = $math->calculateRevaluationPnl('1000000', '4.500000', '4.500002', 6);
        $this->assertEquals('2.000000', $result);

        // Without an override, the internal scale is honored as before.
        $this->assertSame('100.0000', $math->calculateRevaluationPnl('1000', '4.50', '4.60'));
        $this->assertSame('-100.0000', $math->calculateRevaluationPnl('1000', '4.60', '4.50'));

        // Default-scale service keeps its historical output shape.
        $this->assertSame('100.00', $this->math->calculateRevaluationPnl('1000', '4.50', '4.60'));
    }

    #[Test]
    public function round_applies_half_up_on_exact_half(): void
    {
        $this->assertEquals('1.01', $this->math->round('1.005', 2));
        $this->assertEquals('2.35', $this->math->round('2.345', 2));
        $this->assertEquals('3', $this->math->round('2.5'));
        $this->assertEquals('2', $this->math->round('2.4999'));
    }

    #[Test]
    public function round_handles_negatives_with_half_away_from_zero(): void
    {
        $this->assertEquals('-2.68', $this->math->round('-2.675', 2));
        $this->assertEquals('-2.67', $this->math->round('-2.674', 2));
        $this->assertEquals('-3', $this->math->round('-2.5'));
        $this->assertEquals('0.00', $this->math->round('-0.004', 2));
    }

    #[Test]
    public function round_handles_large_values_without_float_precision_loss(): void
    {
        $this->assertEquals('123456789012345.68', $this->math->round('123456789012345.675', 2));
        $this->assertEquals('99999999999999999999.13', $this->math->round('99999999999999999999.125', 2));
        $this->assertEquals('1000000000000.00', $this->math->round('1000000000000.004999', 2));
    }

    #[Test]
    public function safe_divide_returns_zero_for_zero_denominator(): void
    {
        $this->assertEquals('0', $this->math->safeDivide('10.00', '0'));
        $this->assertEquals('0', $this->math->safeDivide('-7.25', '0.00'));
    }

    #[Test]
    public function safe_divide_supports_custom_precision(): void
    {
        // Service scale is 2, but callers can request finer output precision.
        $this->assertEquals('0.333333', $this->math->safeDivide('1', '3', 6));
        $this->assertEquals('3.33333333', $this->math->safeDivide('10', '3', 8));

        // Default falls back to service scale.
        $this->assertEquals('2.50', $this->math->safeDivide('10', '4'));
    }

    #[Test]
    public function abs_returns_absolute_value(): void
    {
        $this->assertEquals('5.25', $this->math->abs('-5.25'));
        $this->assertEquals('5.25', $this->math->abs('5.25'));
        $this->assertEquals('0.00', $this->math->abs('0.00'));
        $this->assertEquals('0.01', $this->math->abs('-0.01'));
    }
}
