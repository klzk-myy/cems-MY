<?php

namespace Tests\Unit\Services\Branch;

use App\Models\TillBalance;
use App\Services\Branch\HandoverVarianceCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandoverVarianceCalculatorTest extends TestCase
{
    private HandoverVarianceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new HandoverVarianceCalculator;
    }

    #[Test]
    public function currency_with_no_prior_balance_has_zero_variance(): void
    {
        $result = $this->calculator->compute(
            [['currency_id' => 'USD', 'amount' => '500.00']],
            ['USD' => 'USD'],
            collect(),
            collect(),
            []
        );

        $this->assertSame('0.0000', $result->perCurrency['USD']);
        $this->assertFalse($result->hasVariance());
    }

    #[Test]
    public function myr_variance_flows_directly_into_total(): void
    {
        $myr = new TillBalance([
            'currency_code' => 'MYR',
            'opening_balance' => '1000.0000',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
        ]);

        $result = $this->calculator->compute(
            [['currency_id' => 'MYR', 'amount' => '1025.50']],
            ['MYR' => 'MYR'],
            collect(['MYR' => $myr]),
            collect(),
            []
        );

        $this->assertSame('25.5000', $result->perCurrency['MYR']);
        $this->assertSame('25.5000', $result->totalMyr);
        $this->assertTrue($result->hasVariance());
        $this->assertStringContainsString('MYR: 25.5000', $result->notes);
    }

    #[Test]
    public function expected_balance_includes_net_foreign_movement(): void
    {
        $usd = new TillBalance([
            'currency_code' => 'USD',
            'opening_balance' => '1000.0000',
            'buy_total_foreign' => '300.0000',
            'sell_total_foreign' => '100.0000',
        ]);

        // Expected = 1000 + 300 - 100 = 1200; count 1150 => variance -50.
        $result = $this->calculator->compute(
            [['currency_id' => 'USD', 'amount' => '1150.0000']],
            ['USD' => 'USD'],
            collect(['USD' => $usd]),
            collect(),
            ['USD' => '4.5000']
        );

        $this->assertSame('-50.0000', $result->perCurrency['USD']);
        $this->assertSame('-225.0000', $result->totalMyr);
    }

    #[Test]
    public function foreign_variance_is_converted_to_myr_with_per_unit_rate(): void
    {
        $myr = new TillBalance([
            'currency_code' => 'MYR',
            'opening_balance' => '100.0000',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
        ]);
        $usd = new TillBalance([
            'currency_code' => 'USD',
            'opening_balance' => '200.0000',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
        ]);

        $result = $this->calculator->compute(
            [
                ['currency_id' => 'MYR', 'amount' => '90.0000'],
                ['currency_id' => 'USD', 'amount' => '210.0000'],
            ],
            ['MYR' => 'MYR', 'USD' => 'USD'],
            collect(['MYR' => $myr, 'USD' => $usd]),
            collect(),
            ['USD' => '4.0000']
        );

        // MYR -10 + USD +10 * 4 = 30 MYR total.
        $this->assertSame('-10.0000', $result->perCurrency['MYR']);
        $this->assertSame('10.0000', $result->perCurrency['USD']);
        $this->assertSame('30.0000', $result->totalMyr);
    }

    #[Test]
    public function closed_balance_row_is_used_when_no_open_balance_exists(): void
    {
        $closed = new TillBalance([
            'currency_code' => 'USD',
            'opening_balance' => '1000.0000',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
            'closed_at' => now(),
        ]);

        $result = $this->calculator->compute(
            [['currency_id' => 'USD', 'amount' => '995.0000']],
            ['USD' => 'USD'],
            collect(),
            collect(['USD' => $closed]),
            ['USD' => '4.5000']
        );

        $this->assertSame('-5.0000', $result->perCurrency['USD']);
        $this->assertSame('-22.5000', $result->totalMyr);
    }

    #[Test]
    public function unresolvable_currency_ids_are_skipped(): void
    {
        $result = $this->calculator->compute(
            [['currency_id' => 'ZZZ', 'amount' => '100.00']],
            [],
            collect(),
            collect(),
            []
        );

        $this->assertSame([], $result->perCurrency);
        $this->assertFalse($result->hasVariance());
    }
}
