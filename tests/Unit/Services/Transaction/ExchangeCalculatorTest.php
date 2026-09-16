<?php

namespace Tests\Unit\Services\Transaction;

use App\Enums\TransactionType;
use App\Services\System\MathService;
use App\Services\Transaction\ExchangeCalculator;
use App\ValueObjects\QuoteConvention;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Characterization tests for ExchangeCalculator — the single source of truth
 * for foreign → local conversion (amount_local = amount_foreign × rate).
 *
 * Expected values are derived from the existing tests that exercised the
 * previously-inlined `MathService::multiply(amount_foreign, rate)`:
 *   - TransactionServicePrepareTest::prepare_and_create_completes_small_transaction
 *     asserts amount_local '450.0000' for amount_foreign '100.00' @ rate '4.500000'.
 *   - MathServiceTest::it_multiplies_two_numbers_with_precision asserts
 *     multiply('10.50', '3') === '31.50' (here at scale 4: '31.5000').
 *
 * ExchangeCalculator rounds half-up to 4 decimals to align with decimal(18,4)
 * storage, but because MathService::multiply already returns a value truncated
 * to its default scale of 4, that rounding is a no-op relative to the former
 * inline behavior — so the financial output is preserved exactly.
 */
class ExchangeCalculatorTest extends TestCase
{
    private function calculator(): ExchangeCalculator
    {
        // Production binding uses the default MathService scale of 4.
        return new ExchangeCalculator(new MathService);
    }

    #[Test]
    public function buy_converts_foreign_to_local_using_teller_entered_rate(): void
    {
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '100.00',
            '4.500000',
        );

        $this->assertSame('450.0000', $result['amount_local']);
    }

    #[Test]
    public function sell_converts_foreign_to_local_using_teller_entered_rate(): void
    {
        $result = $this->calculator()->calculate(
            TransactionType::Sell,
            'USD',
            '2000.00',
            '5.000000',
        );

        $this->assertSame('10000.0000', $result['amount_local']);
    }

    #[Test]
    public function integer_amounts_and_rates_are_supported(): void
    {
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '100',
            '4.50',
        );

        $this->assertSame('450.0000', $result['amount_local']);
    }

    #[Test]
    public function fractional_products_are_rounded_to_four_decimals(): void
    {
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '10.50',
            '3',
        );

        $this->assertSame('31.5000', $result['amount_local']);
    }

    #[Test]
    public function six_decimal_rate_matches_money_cast_format(): void
    {
        // Rate arrives as a 6-decimal string (ExchangeRate MoneyCast ':6').
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '100.00',
            '4.123456',
        );

        $this->assertSame('412.3456', $result['amount_local']);
    }

    #[Test]
    public function rounding_is_a_no_op_relative_to_scale_four_multiplication(): void
    {
        // 1 × 0.555555 → bcmul at scale 4 truncates to '0.5555'; the half-up
        // round to 4 decimals leaves it unchanged (no financial behavior change).
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '1',
            '0.555555',
        );

        $this->assertSame('0.5555', $result['amount_local']);
    }

    #[Test]
    public function returned_array_preserves_amount_foreign_and_rate_unchanged(): void
    {
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'USD',
            '100.00',
            '4.500000',
        );

        $this->assertSame('100.00', $result['amount_foreign']);
        $this->assertSame('4.50000000', $result['rate']);
    }

    #[Test]
    public function buy_and_sell_produce_identical_local_amount_for_same_inputs(): void
    {
        // The rate is caller-supplied; the transaction type must not alter the math.
        $buy = $this->calculator()->calculate(TransactionType::Buy, 'USD', '100.00', '4.500000');
        $sell = $this->calculator()->calculate(TransactionType::Sell, 'USD', '100.00', '4.500000');

        $this->assertSame($buy['amount_local'], $sell['amount_local']);
        $this->assertSame('450.0000', $buy['amount_local']);
    }

    #[Test]
    public function unit_quoted_rate_divides_by_rate_unit_before_multiplying(): void
    {
        // 1,000,000 IDR at RM 235 per 1,000,000 → RM 235.00.
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'IDR',
            '1000000',
            '235',
            null,
            new QuoteConvention(1000000)
        );

        $this->assertSame('235.0000', $result['amount_local']);
        $this->assertSame('0.00023500', $result['rate']);
    }

    #[Test]
    public function unit_quoted_rate_scales_with_the_foreign_amount(): void
    {
        // 500,000 IDR at RM 235 per 1,000,000 → RM 117.50.
        $result = $this->calculator()->calculate(
            TransactionType::Sell,
            'IDR',
            '500000',
            '235',
            null,
            new QuoteConvention(1000000)
        );

        $this->assertSame('117.5000', $result['amount_local']);
        $this->assertSame('0.00023500', $result['rate']);
    }

    #[Test]
    public function unit_quoted_rate_of_one_is_identical_to_default(): void
    {
        $explicit = $this->calculator()->calculate(TransactionType::Buy, 'USD', '100.00', '4.500000', null, new QuoteConvention);
        $implicit = $this->calculator()->calculate(TransactionType::Buy, 'USD', '100.00', '4.500000');

        $this->assertSame($implicit, $explicit);
    }

    #[Test]
    public function inverse_quoted_rate_inverts_the_normalization(): void
    {
        // RM 1 = 4,255 IDR (inverse quote, unit 1) → per-unit truncates to
        // 0.00023501 at 8dp, and amount_local uses that same stored rate.
        $result = $this->calculator()->calculate(
            TransactionType::Buy,
            'IDR',
            '1000000',
            '4255',
            null,
            new QuoteConvention(1, true)
        );

        $this->assertSame('235.0100', $result['amount_local']);
        $this->assertSame('0.00023501', $result['rate']);
    }

    #[Test]
    public function inverse_quoted_rate_with_unit_scales_the_myr_side(): void
    {
        // RM 100 = 425,500 IDR (inverse, unit 100) → per-unit 100/425500,
        // truncated to 0.00023501 at storage precision.
        $result = $this->calculator()->calculate(
            TransactionType::Sell,
            'IDR',
            '425500',
            '425500',
            null,
            new QuoteConvention(100, true)
        );

        $this->assertSame('99.9967', $result['amount_local']);
        $this->assertSame('0.00023501', $result['rate']);
    }
}
