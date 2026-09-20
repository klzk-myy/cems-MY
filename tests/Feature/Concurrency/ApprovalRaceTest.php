<?php

namespace Tests\Feature\Concurrency;

use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionApprovalService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for the approval-path races found in the vertical-slice
 * review (T1 stale reject overwrite, T2 reservation leak on reject,
 * T3 own-reservation double-count, T13 unlocked clearHold).
 *
 * SQLite has no row locks, so interleavings are simulated with stale model
 * instances — see ConcurrentTestCase.
 */
class ApprovalRaceTest extends ConcurrentTestCase
{
    protected User $teller;

    protected User $compliance;

    protected Customer $customer;

    protected Counter $counter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        $this->compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $this->customer = Customer::factory()->create([
            'sanction_hit' => false,
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);
        $this->counter = Counter::factory()->create();

        $this->teller->forceFill(['branch_id' => $this->counter->branch_id])->save();
        $this->compliance->forceFill(['branch_id' => $this->counter->branch_id])->save();

        CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'opened_by' => $this->teller->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);

        TillBalance::factory()->create([
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'MYR',
            'date' => today(),
            'opening_balance' => '1000000.00',
            'opened_by' => $this->teller->id,
            'branch_id' => $this->counter->branch_id,
        ]);

        // The drawer physically holds the FCY being sold — the till floor
        // rejects a Sell exceeding the expected FCY balance.
        TillBalance::factory()->create([
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'USD',
            'date' => today(),
            'opening_balance' => '50000.00',
            'opened_by' => $this->teller->id,
            'branch_id' => $this->counter->branch_id,
        ]);

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
    }

    /**
     * T3: approval must compare the sell quantity against the raw locked
     * position — the transaction's own pending reservation already earmarks
     * the stock and must not be subtracted from its own availability check.
     */
    #[Test]
    public function sell_of_full_remaining_stock_can_be_approved(): void
    {
        // Position exactly equals the sell quantity: under the old
        // getAvailableBalance() check, available = 3000 - 3000 (own
        // reservation) = 0 and the approval falsely failed.
        $this->createPosition('3000.00');
        $transaction = $this->createPendingSell('3000.00');

        $response = $this->actingAs($this->compliance)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve");

        $response->assertStatus(200)->assertJson(['success' => true]);

        $transaction->refresh();
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);

        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('branch_id', (string) $this->counter->branch_id)
            ->first();
        $this->assertEquals('0.0000', $position->quantity);

        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertEquals(StockReservationStatus::Consumed, $reservation->status);
    }

    /**
     * T1: a reject issued with a stale (pre-approval) model snapshot must not
     * overwrite a committed Completed status. The service re-reads the row
     * under a lock and refuses because the live status is not pending.
     */
    #[Test]
    public function reject_cannot_overwrite_committed_completion(): void
    {
        $this->createPosition('10000.00');
        $transaction = $this->createPendingSell('3000.00');

        // Path B's stale snapshot — loaded before the approval commits.
        $stale = $this->staleCopy($transaction);

        // Path A: approve commits Completed plus stock/till/journal effects.
        $this->actingAs($this->compliance)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve")
            ->assertStatus(200);
        $this->assertEquals(TransactionStatus::Completed, $transaction->fresh()->status);

        // Path B: reject with the stale PendingApproval snapshot must throw
        // and must not touch the committed row.
        $service = $this->app->make(TransactionApprovalService::class);

        try {
            $service->reject($stale, $this->compliance->id, 'stale reject');
            $this->fail('Stale reject should have thrown TransactionValidationException');
        } catch (TransactionValidationException) {
            // expected
        }

        $transaction->refresh();
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertNull($transaction->rejection_reason);
    }

    /**
     * T2: rejecting a pending Sell must release its stock reservation
     * immediately — not leave it for the 24-hour expiry sweep.
     */
    #[Test]
    public function rejecting_pending_sell_releases_reservation(): void
    {
        $this->createPosition('10000.00');
        $transaction = $this->createPendingSell('3000.00');

        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertEquals(StockReservationStatus::Pending, $reservation->status);

        $response = $this->actingAs($this->compliance)
            ->postJson("/api/v1/transactions/{$transaction->id}/reject", [
                'reason' => 'Compliance rejection',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $transaction->refresh();
        $this->assertEquals(TransactionStatus::Rejected, $transaction->status);

        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Released, $reservation->status);
    }

    /**
     * T13: two clears on the same hold must not both write clearance — the
     * second sees the locked row's compliance_cleared_at and fails.
     */
    #[Test]
    public function double_clear_hold_is_idempotent(): void
    {
        $this->createPosition('10000.00');
        $transaction = $this->createPendingSell('3000.00');
        $transaction->forceFill(['hold_reason' => 'AML review required'])->save();

        $service = $this->app->make(TransactionApprovalService::class);

        $service->clearHold($transaction, $this->compliance->id);
        $clearedAt = $transaction->fresh()->compliance_cleared_at;
        $this->assertNotNull($clearedAt);

        // Second clear with a stale snapshot must be rejected by the
        // re-read-under-lock check, not silently write a duplicate.
        $stale = $this->staleCopy($transaction);
        $stale->compliance_cleared_at = null; // path B's pre-clear view

        try {
            $service->clearHold($stale, $this->compliance->id);
            $this->fail('Second clearHold should have thrown TransactionValidationException');
        } catch (TransactionValidationException) {
            // expected
        }

        $this->assertEquals(
            $clearedAt->toIso8601String(),
            $transaction->fresh()->compliance_cleared_at->toIso8601String()
        );
    }

    private function createPosition(string $quantity): void
    {
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => (string) $this->counter->branch_id,
            'quantity' => $quantity,
            'average_cost' => '4.50',
            'current_rate' => '4.50',
        ]);
    }

    private function createPendingSell(string $quantity): Transaction
    {
        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $this->customer->id,
                'type' => TransactionType::Sell->value,
                'currency_code' => 'USD',
                'quantity' => $quantity,
                'rate' => '4.50',
                'till_id' => (string) $this->counter->code,
                'purpose' => 'Test transaction',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(201);

        $transaction = Transaction::query()->whereKey($response->json('data.id'))->firstOrFail();
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);

        return $transaction;
    }
}
