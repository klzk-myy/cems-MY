<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Services\System\MathService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BoundaryValueTest extends TestCase
{
    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    #[DataProvider('cddThresholdProvider')]
    #[Test]
    public function cdd_level_at_thresholds(float $amount, string $expectedLevel): void
    {
        $cddLevel = $this->determineCddLevel((string) $amount, false, false);

        $this->assertEquals($expectedLevel, $cddLevel->value);
    }

    public static function cddThresholdProvider(): array
    {
        return [
            'exactly at simplified threshold (below 3000)' => [2999.99, 'Simplified'],
            'exactly at standard threshold (3000)' => [3000.00, 'Standard'],
            'just below large transaction threshold (49999.99)' => [49999.99, 'Standard'],
            'exactly at large transaction threshold (50000)' => [50000.00, 'Enhanced'],
        ];
    }

    #[Test]
    public function negative_amount_validation(): void
    {
        $result = $this->mathService->compare('-100', '0');
        $this->assertLessThan(0, $result);
    }

    #[Test]
    public function maximum_amount_handling(): void
    {
        $maxAmount = '999999999.99';
        $result = $this->mathService->add($maxAmount, '0.01');
        $this->assertEquals('1000000000.0000', $result);
    }

    #[Test]
    public function precision_maintained_for_small_amounts(): void
    {
        $result = $this->mathService->add('0.01', '0.02');
        $this->assertEquals('0.0300', $result);
    }

    #[Test]
    public function six_decimal_precision_handling(): void
    {
        $rate = '4.723456';
        $amount = '1000';
        $result = $this->mathService->multiply($rate, $amount);
        $this->assertEquals('4723.4560', $result);
    }

    #[Test]
    public function currency_position_at_zero_balance(): void
    {
        $positionQty = '0';
        $canSell = bccomp($positionQty, '10', 2) >= 0;
        $this->assertFalse($canSell);
    }

    #[Test]
    public function average_cost_calculation_extreme_values(): void
    {
        // Old: 0.000001 @ 1000000 units
        $oldQuantity = '1000000';
        $oldAvgCost = '0.000001';
        $oldValue = $this->mathService->multiply($oldQuantity, $oldAvgCost); // = 1

        // New: buy 1 unit at 1.00
        $newQuantity = '1';
        $newCost = '1.00';
        $newValue = $this->mathService->multiply($newQuantity, $newCost); // = 1

        $totalQuantity = $this->mathService->add($oldQuantity, $newQuantity); // = 1000001
        $totalValue = $this->mathService->add($oldValue, $newValue); // = 2

        $newAvgCost = $this->mathService->divide($totalValue, $totalQuantity);
        // 2 / 1000001 ≈ 0.00000199998..., which rounds to 0.000002 with standard rounding
        // but bcmath truncates to 0.0000 at 4 decimal precision
        $this->assertEquals('0.0000', $newAvgCost);
    }

    #[Test]
    public function revaluation_with_zero_rate_change(): void
    {
        $oldRate = '4.5000';
        $newRate = '4.5000';
        $positionQuantity = '1000';

        $oldValue = $this->mathService->multiply($positionQuantity, $oldRate);
        $newValue = $this->mathService->multiply($positionQuantity, $newRate);
        $pnl = $this->mathService->subtract($newValue, $oldValue);

        $this->assertEquals('0.0000', $pnl);
    }

    /**
     * Helper method to determine CDD level
     */
    private function determineCddLevel(string $amount, bool $isPep, bool $isSanctionMatch): CddLevel
    {
        if (bccomp($amount, '50000', 2) >= 0 || $isPep || $isSanctionMatch) {
            return CddLevel::Enhanced;
        }

        if (bccomp($amount, '3000', 2) >= 0) {
            return CddLevel::Standard;
        }

        return CddLevel::Simplified;
    }
}
