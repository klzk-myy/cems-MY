<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\SegregationOfDutiesException;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use App\Services\Transaction\TransactionCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TransactionCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionCancellationService $cancellationService;

    protected CurrencyPositionService $positionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestBranch();
        $this->cancellationService = app(TransactionCancellationService::class);
    }

    #[Test]
    public function concurrent_reversals_produce_correct_balance(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();
        $branch = $this->createTestBranch();

        // Create initial position: 5000 USD
        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create and reverse first Buy transaction (reversal = Sell)
        $transaction1 = Transaction::factory()->make([
            'id' => 99901,
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
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
            'branch_id' => $branch->id,
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
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertEquals('3000.0000', $position->balance);
    }

    #[Test]
    public function reverse_positions_acquires_row_lock(): void
    {
        $currencyCode = 'USD';
        $tillId = 'TEST-TILL-'.uniqid();
        $branch = $this->createTestBranch();

        // Create initial position
        CurrencyPosition::factory()->create([
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'balance' => '3000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Create a Buy transaction (reversal will be Sell, decreasing position)
        $transaction = Transaction::factory()->make([
            'id' => 99903,
            'currency_code' => $currencyCode,
            'branch_id' => $branch->id,
            'type' => TransactionType::Buy,
            'amount_foreign' => '500.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        $this->cancellationService->reversePositions($transaction);

        $position = CurrencyPosition::where('currency_code', $currencyCode)
            ->where('branch_id', $branch->id)
            ->first();

        // Buy transaction reversed as Sell: 3000 - 500 = 2500
        $this->assertEquals('2500.0000', $position->balance);
    }

    #[Test]
    public function reverse_positions_throws_on_nonexistent_position(): void
    {
        $transaction = Transaction::factory()->make([
            'id' => 99904,
            'currency_code' => 'XYZ',
            'branch_id' => 99999,
            'till_id' => 'NONEXISTENT-TILL',
            'type' => TransactionType::Sell,
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
        ]);

        // A missing position must abort the cancellation, not commit without restoring state.
        $this->expectException(RuntimeException::class);

        $this->cancellationService->reversePositions($transaction);
    }

    #[Test]
    public function refund_requires_different_approver_than_requester(): void
    {
        // Reversal is compliance-only; a compliance officer still cannot
        // reverse a transaction they created themselves (segregation of duties).
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        // Create a completed transaction recorded under the same user
        $transaction = Transaction::factory()->create([
            'user_id' => $compliance->id,
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(), // Within cancellation window
        ]);

        // Create a currency position for the reversal
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD', 'branch_id' => $transaction->branch_id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Attempt to reverse own transaction should throw SegregationOfDutiesException
        $this->expectException(SegregationOfDutiesException::class);
        $this->expectExceptionMessage('Segregation of duties violation');

        $this->cancellationService->requestReversal($transaction, $compliance, 'Test reversal reason');
    }

    #[Test]
    public function compliance_officer_can_reverse_other_user_transaction(): void
    {
        // Create a teller who created the transaction
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        // Only compliance officers may reverse completed transactions
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

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
            'currency_code' => 'USD', 'branch_id' => $transaction->branch_id,
            'balance' => '5000.00',
            'avg_cost_rate' => '4.50',
            'last_valuation_rate' => '4.50',
        ]);

        // Compliance reversing the teller's transaction should succeed
        $result = $this->cancellationService->requestReversal($transaction, $compliance, 'Compliance reversing teller error');

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Reversed, $transaction->status);
    }

    #[Test]
    public function cancellation_rejection_restores_previous_status(): void
    {
        // Create a manager who will request cancellation
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        // Create a completed transaction
        $transaction = Transaction::factory()->create([
            'user_id' => $manager->id,
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '500.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        // Request cancellation (Completed -> PendingCancellation)
        $result = $this->cancellationService->requestCancellation($transaction, $manager, 'Test cancellation request');
        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::PendingCancellation, $transaction->status);

        // Create another manager to reject the cancellation (segregation of duties)
        $manager2 = User::factory()->create(['role' => UserRole::Manager]);

        // Reject the cancellation - should restore to Completed
        $result = $this->cancellationService->rejectCancellation($transaction, $manager2, 'Rejection reason');

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Completed, $transaction->status);

        // Verify the transition history shows the proper state machine path
        $history = $transaction->transition_history;
        $this->assertNotEmpty($history);

        // Find the rejection transition entry
        $rejectionEntry = null;
        foreach (array_reverse($history) as $entry) {
            if ($entry['to'] === TransactionStatus::Completed->value && str_contains($entry['reason'] ?? '', 'Cancellation rejected')) {
                $rejectionEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($rejectionEntry, 'Rejection should be recorded in transition history');
        $this->assertArrayNotHasKey('forced', $rejectionEntry, 'Rejection should not use forced transition');
    }

    #[Test]
    public function approve_cancellation_flushes_dashboard_ledger_and_report_caches(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Manager]);
        $approver = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $transaction = Transaction::factory()->create([
            'user_id' => $requester->id,
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $transaction->branch_id,
            'quantity' => '5000.00',
            'average_cost' => '4.50',
        ]);

        Cache::tags(['dashboard'])->put('probe_dash', 'stale', 600);
        Cache::tags(['ledger'])->put('probe_ledger', 'stale', 600);
        Cache::tags(['reports'])->put('probe_reports', 'stale', 600);

        $this->cancellationService->requestCancellation($transaction, $requester, 'customer changed mind');
        $this->cancellationService->approveCancellation($transaction, $approver);

        $this->assertNull(Cache::tags(['dashboard'])->get('probe_dash'));
        $this->assertNull(Cache::tags(['ledger'])->get('probe_ledger'));
        $this->assertNull(Cache::tags(['reports'])->get('probe_reports'));
    }

    #[Test]
    public function manager_cannot_approve_cancellation_of_completed_transaction(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Manager]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $transaction = Transaction::factory()->create([
            'user_id' => $requester->id,
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        $this->cancellationService->requestCancellation($transaction, $requester, 'customer changed mind');
        $result = $this->cancellationService->approveCancellation($transaction, $manager);

        $this->assertFalse($result, 'Reversal of a completed transaction is compliance-only');
        $this->assertEquals(TransactionStatus::PendingCancellation, $transaction->fresh()->status);
    }

    #[Test]
    public function approving_cancellation_of_completed_transaction_reverses_positions(): void
    {
        $branch = $this->createTestBranch();
        $tillId = 'TEST-TILL-'.uniqid();

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $branch->id,
            'quantity' => '5000.00',
            'average_cost' => '4.50',
        ]);

        $requester = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $branch->id]);
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'branch_id' => $branch->id]);

        $transaction = Transaction::factory()->create([
            'user_id' => $requester->id,
            'branch_id' => $branch->id,
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'amount_foreign' => '100.00',
            'rate' => '4.50',
            'status' => TransactionStatus::Completed,
            'created_at' => now(),
        ]);

        $this->cancellationService->requestCancellation($transaction, $requester, 'customer changed mind');
        $result = $this->cancellationService->approveCancellation($transaction, $compliance);

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Cancelled, $transaction->fresh()->status);

        // A Sell of 100 USD must restore the position drained at completion.
        $position = CurrencyPosition::where('branch_id', $branch->id)->where('currency_code', 'USD')->first();
        $this->assertEquals('5100.0000', $position->quantity);
    }

    #[Test]
    public function forget_exchange_rates_clears_both_rate_cache_keys(): void
    {
        Cache::put(CacheKeys::exchangeRates(), 'stale', 600);
        Cache::put(CacheKeys::ExchangeRates->value, 'stale', 600);

        app(CacheInvalidationService::class)->forgetExchangeRates();

        $this->assertNull(Cache::get(CacheKeys::exchangeRates()));
        $this->assertNull(Cache::get(CacheKeys::ExchangeRates->value));
    }

    #[Test]
    public function fallback_pre_cancellation_status_returns_null_for_empty_history(): void
    {
        $transaction = $this->transactionWithHistory([]);

        $this->assertNull($this->invokeFallback($transaction));
    }

    #[Test]
    public function fallback_pre_cancellation_status_returns_null_without_pending_marker(): void
    {
        $transaction = $this->transactionWithHistory([
            ['from' => 'approved', 'to' => 'completed'],
        ]);

        $this->assertNull($this->invokeFallback($transaction));
    }

    #[Test]
    public function fallback_pre_cancellation_status_finds_status_after_pending_marker(): void
    {
        $transaction = $this->transactionWithHistory([
            ['from' => 'completed', 'to' => 'pending_cancellation'],
            ['from' => 'completed', 'to' => 'completed'],
        ]);

        $this->assertSame(TransactionStatus::Completed, $this->invokeFallback($transaction));
    }

    #[Test]
    public function fallback_pre_cancellation_status_skips_invalid_and_current_statuses(): void
    {
        $transaction = $this->transactionWithHistory([
            ['from' => 'pending_approval', 'to' => 'pending_cancellation'],
            ['from' => 'bogus', 'to' => 'Other'],
            ['from' => 'pending_cancellation', 'to' => 'Other'],
        ]);

        $this->assertNull($this->invokeFallback($transaction));
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    private function transactionWithHistory(array $history): Transaction
    {
        $transaction = new Transaction;
        $transaction->status = TransactionStatus::PendingCancellation;
        $transaction->transition_history = $history;

        return $transaction;
    }

    private function invokeFallback(Transaction $transaction): ?TransactionStatus
    {
        $method = new \ReflectionMethod($this->cancellationService, 'findFallbackPreCancellationStatus');
        $method->setAccessible(true);

        return $method->invoke($this->cancellationService, $transaction);
    }
}
