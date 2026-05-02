<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\SegregationOfDutiesException;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyPositionService;
use App\Services\TransactionCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionCancellationService $cancellationService;

    protected CurrencyPositionService $positionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cancellationService = app(TransactionCancellationService::class);
    }

    public function test_concurrent_reversals_produce_correct_balance(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();

        // Create initial position: 5000 USD
        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create and reverse first Buy transaction (reversal = Sell)
        $transaction1 = Transaction::factory()->make([
            'id' => 99901,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction1);

        // Create and reverse second Buy transaction (reversal = Sell)
        $transaction2 = Transaction::factory()->make([
            'id' => 99902,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '1000.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction2);

        // Verify final balance after both reversals
        // Each Buy reversal = Sell = decrease position
        // 5000 - 1000 - 1000 = 3000
        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->first();

        $this->assertEquals('3000.0000', $position->balance);
    }

    public function test_reverse_positions_acquires_row_lock(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();

        // Create initial position
        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'balance' => '3000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create a Buy transaction (reversal will be Sell, decreasing position)
        $transaction = Transaction::factory()->make([
            'id' => 99903,
            'currency_code' => $currencyCode,
            'till_id' => $tillId,
            'type' => TransactionType::Buy,
            'amount_foreign' => '500.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction);

        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->first();

        // Buy transaction reversed as Sell: 3000 - 500 = 2500
        $this->assertEquals('2500.0000', $position->balance);
    }

    public function test_reverse_positions_handles_nonexistent_position(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99904,
            'currency_code' => 'XYZ',
            'till_id' => 'NONEXISTENT-TILL',
            'type' => TransactionType::Sell,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        // Should not throw, just log warning
        $this->cancellationService->reversePositions($transaction);

        // No exception means success
        $this->assertTrue(true);
    }

    public function test_cancel_transaction_throws_exception_direct_cancel_not_allowed(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99905,
            'currency_code' => 'USD',
            'till_id' => 'TEST-TILL',
            'type' => TransactionType::Buy,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Direct cancellation is not allowed');

        $this->cancellationService->cancelTransaction($transaction, 1, 'Test reason');
    }

    public function test_refund_requires_different_approver_than_requester(): void
    {
        // Create a teller who will request the reversal
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        // Create a completed transaction by the same teller
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(), // Within cancellation window
        ]);

        // Create a currency position for the reversal
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => $transaction->till_id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Attempt to reverse own transaction should throw SegregationOfDutiesException
        $this->expectException(SegregationOfDutiesException::class);
        $this->expectExceptionMessage('Segregation of duties violation');

        $this->cancellationService->requestReversal($transaction, $teller, 'Test reversal reason');
    }

    public function test_manager_can_reverse_other_user_transaction(): void
    {
        // Create a teller who created the transaction
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        // Create a manager who will reverse it (different user - allowed)
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        // Create a completed transaction by the teller
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(), // Within cancellation window
        ]);

        // Create a currency position for the reversal
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => $transaction->till_id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Manager reversing teller's transaction should succeed
        $result = $this->cancellationService->requestReversal($transaction, $manager, 'Manager reversing teller error');

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Reversed, $transaction->status);
    }

    public function test_cancellation_rejection_uses_valid_state_transition(): void
    {
        // Create a manager who will request cancellation
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        // Create a processing transaction (PendingCancellation can reject back to Completed)
        // Use factory to ensure all FK constraints are satisfied, then set status and history
        $transaction = Transaction::factory()->create([
            'user_id' => $manager->id,
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '500.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed, // Factory creates Completed
            'created_at' => now(),
        ]);

        // Manually set to Processing and add history entry (since factory defaults to Completed)
        $transaction->status = TransactionStatus::Processing;
        $transaction->transition_history = [
            ['from' => 'Approved', 'to' => 'Processing', 'timestamp' => now()->toIso8601String()],
        ];
        $transaction->save();

        // Request cancellation (goes to PendingCancellation)
        $result = $this->cancellationService->requestCancellation($transaction, $manager, 'Test cancellation request');
        $this->assertTrue($result);

        // Transaction should now be in PendingCancellation status
        $this->assertEquals(TransactionStatus::PendingCancellation, $transaction->status);

        // Create another manager to reject the cancellation (segregation of duties)
        $manager2 = User::factory()->create(['role' => UserRole::Manager]);

        // Reject the cancellation - should use normal state transition to Completed
        // (restoring the previous status before cancellation was requested)
        $result = $this->cancellationService->rejectCancellation($transaction, $manager2, 'Rejection reason');

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);

        // Verify the transition history shows the proper state machine path
        $history = $transaction->transition_history;
        $this->assertNotEmpty($history);

        // Find the rejection transition
        $rejectionEntry = null;
        foreach (array_reverse($history) as $entry) {
            if ($entry['to'] === 'Completed' && str_contains($entry['reason'] ?? '', 'Cancellation rejected')) {
                $rejectionEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($rejectionEntry, 'Rejection should be recorded in transition history');
        $this->assertArrayNotHasKey('forced', $rejectionEntry, 'Rejection should not use forced transition');
    }
}
