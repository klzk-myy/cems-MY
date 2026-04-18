<?php

namespace Tests\Unit;

use App\Services\MathService;
use Tests\TestCase;

class MathServiceTest extends TestCase
{
    protected MathService $math;

    protected function setUp(): void
    {
        parent::setUp();
        // Use scale 2 for currency operations
        $this->math = new MathService(2);
    }

    /** @test */
    public function it_adds_two_numbers_with_precision(): void
    {
        $result = $this->math->add('10.50', '5.25');
        $this->assertEquals('15.75', $result);
    }

    /** @test */
    public function it_subtracts_two_numbers_with_precision(): void
    {
        $result = $this->math->subtract('100.00', '25.50');
        $this->assertEquals('74.50', $result);
    }

    /** @test */
    public function it_multiplies_two_numbers_with_precision(): void
    {
        $result = $this->math->multiply('10.50', '3');
        $this->assertEquals('31.50', $result);
    }

    /** @test */
    public function it_divides_two_numbers_with_precision(): void
    {
        $result = $this->math->divide('100.00', '4');
        $this->assertEquals('25.00', $result);
    }

    /** @test */
    public function it_throws_exception_for_division_by_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');
        $this->math->divide('100.00', '0');
    }

    /** @test */
    public function it_compares_two_decimals_correctly(): void
    {
        $this->assertEquals(0, $this->math->compare('10.50', '10.50'));
        $this->assertEquals(-1, $this->math->compare('10.50', '10.51'));
        $this->assertEquals(1, $this->math->compare('10.50', '10.49'));
    }

    /** @test */
    public function it_handles_large_numbers(): void
    {
        $result = $this->math->add('999999999999.99', '0.01');
        $this->assertEquals('1000000000000.00', $result);
    }

    /** @test */
    public function it_calculates_weighted_average_cost(): void
    {
        // Old: 1000 USD at 4.50 = 4500 MYR
        // New: 500 USD at 4.60 = 2300 MYR
        // Total: 1500 USD at (4500 + 2300) / 1500 = 4.533...
        $result = $this->math->calculateAverageCost('1000', '4.50', '500', '4.60');
        $this->assertEquals('4.53', substr($result, 0, 4));
    }

    /** @test */
    public function it_calculates_revaluation_pnl(): void
    {
        // 1000 USD * (4.60 - 4.50) = 100 MYR gain
        $result = $this->math->calculateRevaluationPnl('1000', '4.50', '4.60');
        $this->assertEquals('100.00', $result);
    }

    /** @test */
    public function it_calculates_transaction_amount(): void
    {
        // 100 USD * 4.50 = 450 MYR
        $result = $this->math->calculateTransactionAmount('100', '4.50');
        $this->assertEquals('450.00', $result);
    }
}
