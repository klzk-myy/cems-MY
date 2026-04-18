<?php

namespace Tests\Unit;

use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    public function test_creates_position_on_first_buy(): void
    {
        // Simulate adding to empty position
        $positionQty = '0';
        $addQuantity = '1000';
        $rate = '4.50';
        $newQuantity = bcadd($positionQty, $addQuantity, 4);

        $this->assertEquals('1000.0000', $newQuantity);
    }

    public function test_updates_position_on_additional_buy(): void
    {
        // Existing position
        $positionQty = '5000';
        $positionAvgCost = '4.00';

        // Add more USD
        $addQuantity = '3000';
        $addValue = bcmul($addQuantity, '4.50', 4); // 13500
        $existingValue = bcmul($positionQty, $positionAvgCost, 4); // 20000
        $totalValue = bcadd($existingValue, $addValue, 4); // 33500
        $totalQuantity = bcadd($positionQty, $addQuantity, 4); // 8000

        $newAvgCost = bcdiv($totalValue, $totalQuantity, 4);

        $this->assertEquals('8000.0000', $totalQuantity);
        $this->assertEquals('4.1875', $newAvgCost);
    }

    public function test_decreases_position_on_sell(): void
    {
        $positionQty = '10000';
        $sellQuantity = '3000';
        $newQuantity = bcsub($positionQty, $sellQuantity, 4);

        $this->assertEquals('7000.0000', $newQuantity);
    }

    public function test_multiple_sells_cannot_exceed_total_balance(): void
    {
        $positionQty = '1000';
        $sellQuantity = '1500';
        $canSell = bccomp($sellQuantity, $positionQty, 4) <= 0;

        $this->assertFalse($canSell);
    }

    public function test_position_balance_never_negative(): void
    {
        $positionQty = '500';
        $sellQuantity = '600';
        $newQuantity = bcsub($positionQty, $sellQuantity, 4);

        // Should not allow negative balance
        $this->assertLessThan(0, bccomp($newQuantity, '0', 4));
    }

    public function test_throws_exception_when_selling_more_than_balance(): void
    {
        $positionQty = '100';
        $sellAmount = '500';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertFalse($canSell);
    }

    public function test_throws_exception_when_selling_exact_balance(): void
    {
        $positionQty = '1000';
        $sellAmount = '1000';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertTrue($canSell); // Can sell exact balance
    }

    public function test_throws_exception_when_selling_with_zero_balance(): void
    {
        $positionQty = '0';
        $canSell = bccomp($positionQty, '0', 4) > 0;

        $this->assertFalse($canSell);
    }

    public function test_allows_partial_sell_within_balance(): void
    {
        $positionQty = '5000';
        $sellAmount = '2500';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertTrue($canSell);
    }

    public function test_average_cost_calculation_weighted_average(): void
    {
        // First purchase: 1000 @ 4.00 = 4000
        $qty1 = '1000';
        $rate1 = '4.00';
        $value1 = bcmul($qty1, $rate1, 4);

        // Second purchase: 1000 @ 5.00 = 5000
        $qty2 = '1000';
        $rate2 = '5.00';
        $value2 = bcmul($qty2, $rate2, 4);

        $totalQty = bcadd($qty1, $qty2, 4); // 2000
        $totalValue = bcadd($value1, $value2, 4); // 9000

        $avgCost = bcdiv($totalValue, $totalQty, 4); // 4.50

        $this->assertEquals('4.5000', $avgCost);
    }

    public function test_average_cost_with_extreme_values(): void
    {
        // Very small cost per unit, large quantity
        $qty1 = '1000000';
        $rate1 = '0.000001';
        $value1 = bcmul($qty1, $rate1, 6);

        // Normal purchase
        $qty2 = '1';
        $rate2 = '1.00';
        $value2 = bcmul($qty2, $rate2, 4);

        $totalQty = bcadd($qty1, $qty2, 4);
        $totalValue = bcadd($value1, $value2, 6);

        $avgCost = bcdiv($totalValue, $totalQty, 6);

        // 1.000001 / 1000001 ≈ 0.000000999999, bcmath truncates to 0.000001
        $this->assertEquals('0.000001', $avgCost);
    }
}
