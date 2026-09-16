<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\QuoteConvention;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteConventionTest extends TestCase
{
    #[Test]
    public function direct_convention_divides_quoted_rate_by_unit(): void
    {
        $convention = new QuoteConvention(1000000);

        $this->assertSame('0.00023500', $convention->toPerUnit('235'));
        $this->assertSame('235.00000000', $convention->fromPerUnit('0.000235'));
        $this->assertSame('0.00235000', (new QuoteConvention(100000))->toPerUnit('235'));
    }

    #[Test]
    public function unit_one_direct_is_a_normalized_passthrough(): void
    {
        $convention = new QuoteConvention;

        $this->assertSame('4.50000000', $convention->toPerUnit('4.5'));
        $this->assertSame('4.50000000', $convention->fromPerUnit('4.5'));
    }

    #[Test]
    public function inverse_convention_inverts_the_normalization(): void
    {
        $convention = new QuoteConvention(1, true);

        // RM 1 = 4,255 IDR → per-unit truncates to 8 decimals.
        $this->assertSame('0.00023501', $convention->toPerUnit('4255'));
        $this->assertSame('4255.13807923', $convention->fromPerUnit('0.00023501'));
    }

    #[Test]
    public function inverse_convention_scales_the_myr_side(): void
    {
        $convention = new QuoteConvention(100, true);

        // RM 100 = 425,500 IDR → same per-unit rate as RM 1 = 4,255 IDR.
        $this->assertSame('0.00023501', $convention->toPerUnit('425500'));
        $this->assertSame('0.23501762', (new QuoteConvention(1, true))->toPerUnit('4.255'));
    }

    #[Test]
    public function requote_into_identical_convention_returns_unchanged(): void
    {
        $convention = new QuoteConvention(1000000);

        $this->assertSame('235', $convention->reQuoteInto('235', new QuoteConvention(1000000)));
    }

    #[Test]
    public function requote_between_direct_units_preserves_value(): void
    {
        // 235 per 1,000,000 re-quoted per 100,000 is 23.5.
        $from = new QuoteConvention(1000000);
        $to = new QuoteConvention(100000);

        $this->assertSame('23.50000000', $from->reQuoteInto('235', $to));
    }

    #[Test]
    public function requote_between_directions_bridges_through_per_unit(): void
    {
        // RM 235 per 1,000,000 IDR re-quoted inverse per RM 1 ≈ 4,255.32 IDR.
        $direct = new QuoteConvention(1000000);
        $inverse = new QuoteConvention(1, true);

        $this->assertSame('4255.31914893', $direct->reQuoteInto('235', $inverse));

        // Back again: the round-trip keeps 8-decimal precision.
        $this->assertSame('235.00000000', $inverse->reQuoteInto('4255.31914893', $direct));
    }

    #[Test]
    public function for_builds_from_any_carrier_with_rate_columns(): void
    {
        $carrier = (object) ['rate_unit' => '50000', 'rate_inverse' => 1];
        $convention = QuoteConvention::for($carrier);

        $this->assertSame(50000, $convention->unit);
        $this->assertTrue($convention->inverse);
    }

    #[Test]
    public function for_defaults_to_unit_one_direct_when_carrier_is_null(): void
    {
        $convention = QuoteConvention::for(null);

        $this->assertSame(1, $convention->unit);
        $this->assertFalse($convention->inverse);
    }
}
