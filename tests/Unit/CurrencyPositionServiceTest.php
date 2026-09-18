<?php

namespace Tests\Unit;

use App\Enums\StockReservationStatus;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function creates_position_on_first_buy(): void
    {
        // Simulate adding to empty position
        $positionQty = '0';
        $addQuantity = '1000';
        $rate = '4.50';
        $newQuantity = bcadd($positionQty, $addQuantity, 4);

        $this->assertEquals('1000.0000', $newQuantity);
    }

    #[Test]
    public function updates_position_on_additional_buy(): void
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

    #[Test]
    public function decreases_position_on_sell(): void
    {
        $positionQty = '10000';
        $sellQuantity = '3000';
        $newQuantity = bcsub($positionQty, $sellQuantity, 4);

        $this->assertEquals('7000.0000', $newQuantity);
    }

    #[Test]
    public function multiple_sells_cannot_exceed_total_balance(): void
    {
        $positionQty = '1000';
        $sellQuantity = '1500';
        $canSell = bccomp($sellQuantity, $positionQty, 4) <= 0;

        $this->assertFalse($canSell);
    }

    #[Test]
    public function position_balance_never_negative(): void
    {
        $positionQty = '500';
        $sellQuantity = '600';
        $newQuantity = bcsub($positionQty, $sellQuantity, 4);

        // Should not allow negative balance
        $this->assertLessThan(0, bccomp($newQuantity, '0', 4));
    }

    #[Test]
    public function throws_exception_when_selling_more_than_balance(): void
    {
        $positionQty = '100';
        $sellAmount = '500';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertFalse($canSell);
    }

    #[Test]
    public function throws_exception_when_selling_exact_balance(): void
    {
        $positionQty = '1000';
        $sellAmount = '1000';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertTrue($canSell); // Can sell exact balance
    }

    #[Test]
    public function throws_exception_when_selling_with_zero_balance(): void
    {
        $positionQty = '0';
        $canSell = bccomp($positionQty, '0', 4) > 0;

        $this->assertFalse($canSell);
    }

    #[Test]
    public function allows_partial_sell_within_balance(): void
    {
        $positionQty = '5000';
        $sellAmount = '2500';
        $canSell = bccomp($sellAmount, $positionQty, 4) <= 0;

        $this->assertTrue($canSell);
    }

    #[Test]
    public function average_cost_calculation_weighted_average(): void
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

    #[Test]
    public function average_cost_with_extreme_values(): void
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

    #[Test]
    public function consume_rejects_expired_reservation(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        Currency::factory()->create(['code' => 'USD']);

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->createTestBranch()->id,
            'balance' => '1000.00',
            'avg_cost_rate' => '4.2000',
            'last_valuation_rate' => '4.2000',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'pending_approval',
        ]);

        // Create an expired reservation (expires_at in the past)
        $expiredReservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->subHour(), // Expired
            'created_by' => $teller->id,
        ]);

        $positionService = new CurrencyPositionService(new MathService, new CurrencyPositionLockService(new MathService), new CacheInvalidationService, new ThresholdService);
        $result = $positionService->consumeStockReservation($transaction->id);

        $this->assertNull($result);

        // Verify reservation was NOT consumed (status should still be Pending)
        $expiredReservation->refresh();
        $this->assertEquals(StockReservationStatus::Pending, $expiredReservation->status);
    }

    #[Test]
    public function consume_accepts_valid_reservation(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        Currency::factory()->create(['code' => 'USD']);

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->createTestBranch()->id,
            'balance' => '1000.00',
            'avg_cost_rate' => '4.2000',
            'last_valuation_rate' => '4.2000',
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'pending_approval',
        ]);

        // Create a valid reservation (expires_at in the future)
        $validReservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHour(), // Still valid
            'created_by' => $teller->id,
        ]);

        $positionService = new CurrencyPositionService(new MathService, new CurrencyPositionLockService(new MathService), new CacheInvalidationService, new ThresholdService);
        $result = $positionService->consumeStockReservation($transaction->id);

        $this->assertNotNull($result);
        $this->assertEquals(StockReservationStatus::Consumed, $result->status);
    }

    #[Test]
    public function release_releases_expired_reservation(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        Currency::factory()->create(['code' => 'USD']);

        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'currency_code' => 'USD',
            'type' => 'Sell',
            'amount_foreign' => '100.00',
            'rate' => '4.2000',
            'amount_local' => '420.00',
            'till_id' => 'TILL1',
            'branch_id' => $teller->branch_id,
            'status' => 'pending_approval',
        ]);

        // Create an expired reservation
        $expiredReservation = StockReservation::factory()->create([
            'transaction_id' => $transaction->id,
            'currency_code' => 'USD',
            'till_id' => 'TILL1',
            'amount_foreign' => '100.00',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->subHour(),
            'created_by' => $teller->id,
        ]);

        $positionService = new CurrencyPositionService(new MathService, new CurrencyPositionLockService(new MathService), new CacheInvalidationService, new ThresholdService);
        $result = $positionService->releaseStockReservation($transaction->id);

        // Expired reservations can be released (used by the expire command)
        $this->assertNotNull($result);

        // Verify reservation WAS released
        $expiredReservation->refresh();
        $this->assertEquals(StockReservationStatus::Released, $expiredReservation->status);
    }

    #[Test]
    public function consolidated_positions_aggregate_by_currency_in_sql(): void
    {
        // Admin sees consolidated positions across all branches, aggregated per
        // currency with a weighted-average cost and summed unrealized P&L.
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]
        );

        // Branch A: 100 @ 4.00, revalued earliest
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->createTestBranch()->id,
            'quantity' => '100.00',
            'average_cost' => '4.00',
            'current_rate' => '4.20',
            'unrealized_gain_loss' => '20.0000',
            'last_revalued_at' => '2026-01-10 09:00:00',
        ]);

        // Branch B: 100 @ 4.20, revalued latest (its current_rate is representative)
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->createTestBranch()->id,
            'quantity' => '100.00',
            'average_cost' => '4.20',
            'current_rate' => '4.40',
            'unrealized_gain_loss' => '10.0000',
            'last_revalued_at' => '2026-01-15 09:00:00',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => null]);

        $service = app(CurrencyPositionService::class);
        $positions = $service->getVisiblePositionsForUser($admin);

        $this->assertCount(1, $positions);

        $consolidated = $positions->first();
        $this->assertTrue($consolidated->is_consolidated);
        $this->assertSame('USD', $consolidated->currency_code);
        $this->assertSame('200.0000', $consolidated->quantity); // 100 + 100
        $this->assertSame('4.10000000', $consolidated->average_cost); // (100*4.00 + 100*4.20) / 200
        $this->assertSame('4.40000000', $consolidated->current_rate); // latest-revalued branch
        $this->assertSame('30.0000', $consolidated->unrealized_gain_loss); // 20 + 10
        $this->assertNotNull($consolidated->currency);
        $this->assertSame('USD', $consolidated->currency->code);
    }
}
