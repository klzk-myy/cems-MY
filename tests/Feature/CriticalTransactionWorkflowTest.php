<?php

namespace Tests\Feature;

use App\Enums\CddLevel;
use App\Enums\CounterSessionStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for critical transaction workflows
 *
 * These tests verify:
 * - Segregation of duties (self-approval prevention)
 * - Stock reservation and release
 * - Transaction state machine transitions
 * - Concurrent transaction handling
 * - Threshold consistency
 */
class CriticalTransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teller;

    protected User $manager;

    protected User $admin;

    protected Customer $customer;

    protected Counter $counter;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        $this->manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        // Create test data
        $this->customer = Customer::factory()->create([
            'sanction_hit' => false,
        ]);
        $this->counter = Counter::factory()->create();
        $this->currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        // Open counter session for teller
        $this->openCounterSession($this->teller);
    }

    /**
     * Test: Segregation of Duties - Teller cannot approve their own transaction
     *
     * CRITICAL: BNM AML/CFT compliance requirement
     */
    public function test_teller_cannot_approve_own_transaction(): void
    {
        // Create a transaction as teller (requires approval since >= RM 3,000)
        $transaction = $this->createPendingTransaction($this->teller, '5000.00');

        // Attempt to approve as the same teller (should fail)
        $response = $this->actingAs($this->teller)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot approve your own transaction. Segregation of duties requires a different approver.',
            ]);

        // Verify transaction is still pending
        $transaction->refresh();
        $this->assertEquals(TransactionStatus::PendingApproval, $transaction->status);
    }

    /**
     * Test: Segregation of Duties - Manager can approve teller's transaction
     */
    public function test_manager_can_approve_teller_transaction(): void
    {
        // Create a transaction as teller
        $transaction = $this->createPendingTransaction($this->teller, '5000.00');

        // Approve as manager (different user)
        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify transaction is completed
        $transaction->refresh();
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);
        $this->assertEquals($this->manager->id, $transaction->approved_by);
    }

    /**
     * Test: Stock reservation is released when transaction is cancelled
     *
     * CRITICAL: Prevents stock from being blocked indefinitely
     */
    public function test_stock_reservation_released_on_cancellation(): void
    {
        // Create initial position
        $this->createPosition('10000.00');

        // Create a pending transaction (requires approval)
        $transaction = $this->createPendingTransaction($this->teller, '5000.00');

        // Verify stock reservation was created
        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(StockReservationStatus::Pending, $reservation->status);

        // Get available balance before cancellation
        $availableBefore = $this->getAvailableBalance();

        // Cancel the transaction
        $this->actingAs($this->manager)
            ->postJson("/api/v1/transactions/{$transaction->id}/cancel", [
                'reason' => 'Test cancellation',
            ]);

        // Verify reservation is released
        $reservation->refresh();
        $this->assertEquals(StockReservationStatus::Released, $reservation->status);

        // Verify available balance is restored
        $availableAfter = $this->getAvailableBalance();
        $this->assertEquals('10000.00', $availableAfter);
    }

    /**
     * Test: Transaction state machine prevents dangerous transitions
     *
     * CRITICAL: PendingCancellation should NOT transition to Approved/Processing/Completed
     */
    public function test_pending_cancellation_cannot_transition_to_approved(): void
    {
        // Create and request cancellation of a transaction
        $transaction = $this->createPendingTransaction($this->teller, '5000.00');

        $this->actingAs($this->manager)
            ->postJson("/api/v1/transactions/{$transaction->id}/request-cancellation", [
                'reason' => 'Test cancellation request',
            ]);

        $transaction->refresh();
        $this->assertEquals(TransactionStatus::PendingCancellation, $transaction->status);

        // Attempt to approve (should fail - can only go to Cancelled)
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve");

        $response->assertStatus(400);

        // Verify still in PendingCancellation
        $transaction->refresh();
        $this->assertEquals(TransactionStatus::PendingCancellation, $transaction->status);
    }

    /**
     * Test: Concurrent transactions respect stock reservations
     *
     * CRITICAL: Prevents overselling
     */
    public function test_concurrent_transactions_respect_stock_reservations(): void
    {
        // Create initial position with limited stock
        $this->createPosition('1000.00');

        // Create first pending transaction (reserves 600)
        $transaction1 = $this->createPendingTransaction($this->teller, '600.00');

        // Verify reservation exists
        $this->assertDatabaseHas('stock_reservations', [
            'transaction_id' => $transaction1->id,
            'amount_foreign' => '600.00',
            'status' => StockReservationStatus::Pending->value,
        ]);

        // Available balance should be 400 (1000 - 600 reserved)
        $available = $this->getAvailableBalance();
        $this->assertEquals('400.00', $available);

        // Attempt to create second transaction for 500 (should fail - insufficient available)
        $response = $this->actingAs($this->teller)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $this->customer->id,
                'type' => TransactionType::Sell->value,
                'currency_code' => 'USD',
                'amount_foreign' => '500.00',
                'rate' => '4.50',
                'till_id' => $this->counter->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Insufficient stock available',
            ]);
    }

    /**
     * Test: Thresholds are consistently applied
     *
     * Transactions >= RM 3,000 should require approval
     */
    public function test_threshold_consistency_for_approval_requirement(): void
    {
        // Transaction < RM 3,000 should be auto-approved
        $smallTransaction = $this->createTransaction($this->teller, '2999.99');
        $this->assertEquals(TransactionStatus::Completed, $smallTransaction->status);

        // Transaction >= RM 3,000 should require approval
        $largeTransaction = $this->createTransaction($this->teller, '3000.00');
        $this->assertEquals(TransactionStatus::PendingApproval, $largeTransaction->status);
    }

    /**
     * Test: CDD level determination uses correct thresholds
     */
    public function test_cdd_level_determination_thresholds(): void
    {
        // < RM 3,000 = Simplified
        $this->assertEquals(CddLevel::Simplified, $this->getCddLevel('2999.99'));

        // >= RM 3,000 = Standard
        $this->assertEquals(CddLevel::Standard, $this->getCddLevel('3000.00'));
        $this->assertEquals(CddLevel::Standard, $this->getCddLevel('49999.99'));

        // >= RM 50,000 = Enhanced
        $this->assertEquals(CddLevel::Enhanced, $this->getCddLevel('50000.00'));

        // PEP customer = Enhanced regardless of amount
        $pepCustomer = Customer::factory()->create(['pep_status' => true]);
        $this->assertEquals(CddLevel::Enhanced, $this->getCddLevel('1000.00', $pepCustomer));
    }

    /**
     * Test: Stock reservation expiry prevents indefinite blocking
     */
    public function test_expired_stock_reservations_are_ignored(): void
    {
        // Create position
        $this->createPosition('10000.00');

        // Create a pending transaction
        $transaction = $this->createPendingTransaction($this->teller, '5000.00');

        // Verify reservation was created
        $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($reservation);

        // Expire the reservation (simulate time passing)
        $reservation->update(['expires_at' => now()->subHour()]);

        // Available balance should now include the expired reservation amount
        $available = $this->getAvailableBalance();
        $this->assertEquals('10000.00', $available);
    }

    /**
     * Test: Transaction approval updates position correctly
     */
    public function test_approval_updates_position_and_till_balance(): void
    {
        // Create initial position
        $this->createPosition('10000.00');

        // Create pending transaction
        $transaction = $this->createPendingTransaction($this->teller, '3000.00', TransactionType::Sell);

        // Get initial position
        $initialPosition = CurrencyPosition::where('currency_code', 'USD')
            ->where('till_id', (string) $this->counter->id)
            ->first();
        $this->assertEquals('10000.00', $initialPosition->balance);

        // Approve transaction
        $this->actingAs($this->manager)
            ->postJson("/api/v1/transactions/{$transaction->id}/approve");

        // Verify position was updated
        $finalPosition = CurrencyPosition::where('currency_code', 'USD')
            ->where('till_id', (string) $this->counter->id)
            ->first();
        $this->assertEquals('7000.00', $finalPosition->balance);

        // Verify till balance was updated
        $tillBalance = TillBalance::where('till_id', (string) $this->counter->id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->first();
        $this->assertNotNull($tillBalance);
    }

    // Helper methods

    private function openCounterSession(User $user): void
    {
        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'session_date' => today(),
            'opened_at' => now(),
            'status' => CounterSessionStatus::Open,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'MYR',
            'date' => today(),
            'opening_balance' => '10000.00',
            'opened_by' => $user->id,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'USD',
            'date' => today(),
            'opening_balance' => '0',
            'opened_by' => $user->id,
        ]);
    }

    private function createPosition(string $amount): void
    {
        CurrencyPosition::create([
            'currency_code' => 'USD',
            'till_id' => (string) $this->counter->id,
            'balance' => $amount,
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);
    }

    private function createPendingTransaction(User $user, string $amount, TransactionType $type = TransactionType::Sell): Transaction
    {
        $response = $this->actingAs($user)
            ->postJson('/api/v1/transactions', [
                'customer_id' => $this->customer->id,
                'type' => $type->value,
                'currency_code' => 'USD',
                'amount_foreign' => $amount,
                'rate' => '4.50',
                'till_id' => (string) $this->counter->id,
                'purpose' => 'Test transaction',
                'source_of_funds' => 'Salary',
            ]);

        $response->assertStatus(201);

        return Transaction::latest()->first();
    }

    private function createTransaction(User $user, string $amount): Transaction
    {
        return $this->createPendingTransaction($user, $amount);
    }

    private function getAvailableBalance(): string
    {
        $position = CurrencyPosition::where('currency_code', 'USD')
            ->where('till_id', (string) $this->counter->id)
            ->first();

        $balance = $position ? $position->balance : '0';

        $reserved = StockReservation::where('currency_code', 'USD')
            ->where('till_id', (string) $this->counter->id)
            ->where('status', StockReservationStatus::Pending)
            ->where('expires_at', '>', now())
            ->sum('amount_foreign');

        return bcsub($balance, (string) $reserved, 4);
    }

    private function getCddLevel(string $amount, ?Customer $customer = null): CddLevel
    {
        $customer = $customer ?? $this->customer;
        $amountLocal = bcmul($amount, '4.50', 4);

        return CddLevel::determine($amountLocal, $customer->pep_status, $customer->sanction_hit ?? false, $customer->risk_rating);
    }
}
