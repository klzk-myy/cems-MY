<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Transaction\TransactionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected User $user;

    protected Currency $currency;

    protected Branch $branch;

    protected Counter $counter;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService;

        $this->user = User::factory()->create(['role' => UserRole::Admin]);

        $this->branch = Branch::factory()->create([
            'code' => 'HQ-TEST',
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter',
            'code' => 'CTR-TEST',
            'branch_id' => $this->branch->id,
        ]);

        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->customer = Customer::factory()->create([
            'full_name' => 'Test Customer',
            'id_type' => 'MyKad',
            'id_number_encrypted' => encrypt('123456789012'),
            'nationality' => 'MY',
            'date_of_birth' => '1990-01-15',
            'risk_rating' => 'low',
            'cdd_level' => 'Simplified',
            'is_active' => true,
        ]);
    }

    protected function createTransaction(TransactionStatus $status = TransactionStatus::Draft): Transaction
    {
        return Transaction::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'till_id' => $this->counter->code,
            'type' => TransactionType::Buy,
            'currency_code' => $this->currency->code,
            'amount_myr' => '1000.00',
            'quantity' => '285.7143',
            'rate' => '3.50',
            'status' => $status->value,
            'cdd_level' => 'Simplified',
        ]);
    }

    #[Test]
    public function force_status_logs_to_audit_trail(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);

        // Manually set auth for this test since forceStatus uses auth()->id()
        auth()->login($this->user);

        $stateMachine = new TransactionStateMachine($transaction, $this->auditService);

        $result = $stateMachine->forceStatus(TransactionStatus::Completed, 'Admin override for testing', $this->user);

        $this->assertTrue($result);

        // Verify audit log entry was created
        $auditEntry = SystemLog::where('entity_type', 'Transaction')
            ->where('entity_id', $transaction->id)
            ->where('action', 'force_status_override')
            ->first();

        $this->assertNotNull($auditEntry, 'Audit entry should be created for force_status_override');
        $this->assertEquals($transaction->id, $auditEntry->entity_id);
        $this->assertEquals('Admin override for testing', $auditEntry->new_values['reason'] ?? $auditEntry->description);
        $this->assertEquals($this->user->id, $auditEntry->user_id);
    }

    #[Test]
    public function force_status_without_audit_service_still_saves(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);

        // Pass null as audit service (backwards compatible)
        $stateMachine = new TransactionStateMachine($transaction, null);

        $result = $stateMachine->forceStatus(TransactionStatus::Cancelled, 'Force cancel without audit', $this->user);

        $this->assertTrue($result);
        $this->assertEquals(TransactionStatus::Cancelled, $transaction->status);
    }

    #[Test]
    public function approve_and_complete_refund_transaction_throws_validation_exception(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::PendingApproval);
        $transaction->is_refund = true;
        $transaction->save();

        $stateMachine = new TransactionStateMachine($transaction, $this->auditService);

        // Note: TransactionValidationException's constructor masks custom
        // messages behind a default ('Transaction validation failed'), so
        // only the exception type is asserted here.
        $this->expectException(TransactionValidationException::class);

        try {
            $stateMachine->approveAndComplete('Test refund', $this->user);
        } catch (TransactionValidationException $e) {
            $this->assertSame(
                TransactionStatus::PendingApproval,
                $transaction->fresh()->status,
                'Refund guard must leave state unchanged'
            );

            throw $e;
        }
    }

    #[Test]
    public function release_method_has_been_removed(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Draft);
        $stateMachine = new TransactionStateMachine($transaction);

        // The release() method was removed because it was dead code - it transitioned
        // PendingApproval -> PendingApproval which is a no-op and was never called
        $this->assertFalse(
            method_exists($stateMachine, 'release'),
            'release() method should not exist on TransactionStateMachine'
        );
    }

    /**
     * Iterate the full TRANSITIONS map: every listed transition must succeed
     * from a fresh transaction in the source state, persist the new status,
     * append a history entry and write the per-target audit metadata.
     */
    #[Test]
    public function transitions_matrix_every_listed_transition_succeeds_and_writes_metadata(): void
    {
        $transitions = (new \ReflectionClass(TransactionStateMachine::class))
            ->getConstant('TRANSITIONS');

        $this->assertNotEmpty($transitions, 'TRANSITIONS map must not be empty');

        foreach ($transitions as $from => $targets) {
            foreach ($targets as $target) {
                $transaction = $this->createTransaction(TransactionStatus::from($from));
                $machine = new TransactionStateMachine($transaction, $this->auditService);

                $this->assertTrue(
                    $machine->canTransitionTo(TransactionStatus::from($target)),
                    "canTransitionTo({$from} -> {$target}) should be allowed"
                );

                $result = $machine->transitionTo(
                    TransactionStatus::from($target),
                    ['reason' => "matrix {$from}->{$target}", 'user_id' => $this->user->id]
                );

                $fresh = $transaction->fresh();

                $this->assertTrue($result, "Transition {$from} -> {$target} should succeed");
                $this->assertSame(
                    $target,
                    $fresh->status->value,
                    "Transition {$from} -> {$target} should persist status"
                );

                // History entry appended with expected shape
                $history = $fresh->transition_history;
                $this->assertNotEmpty($history, "History should be written for {$from} -> {$target}");
                $entry = end($history);
                $this->assertSame($from, $entry['from'], "History 'from' mismatch for {$from} -> {$target}");
                $this->assertSame($target, $entry['to'], "History 'to' mismatch for {$from} -> {$target}");
                $this->assertSame($this->user->id, $entry['user_id'], "History user_id mismatch for {$from} -> {$target}");
                $this->assertSame("matrix {$from}->{$target}", $entry['reason']);
                $this->assertArrayHasKey('timestamp', $entry);

                // Optimistic concurrency version bump on every transition
                $this->assertEquals(1, $fresh->version, "Version should bump once for {$from} -> {$target}");

                // Per-target metadata
                match ($target) {
                    'approved' => $this->assertTrue(isset($fresh->approved_by, $fresh->approved_at), "Approved metadata missing for {$from} -> {$target}"),
                    'cancelled' => $this->assertTrue(isset($fresh->cancelled_by, $fresh->cancelled_at) && $fresh->cancellation_reason === "matrix {$from}->{$target}", "Cancelled metadata missing for {$from} -> {$target}"),
                    'failed' => $this->assertSame("matrix {$from}->{$target}", $fresh->failure_reason, "Failed metadata missing for {$from} -> {$target}"),
                    'rejected' => $this->assertSame("matrix {$from}->{$target}", $fresh->rejection_reason, "Rejected metadata missing for {$from} -> {$target}"),
                    'reversed' => $this->assertSame("matrix {$from}->{$target}", $fresh->reversal_reason, "Reversed metadata missing for {$from} -> {$target}"),
                    default => null,
                };
            }
        }
    }

    /**
     * A sample of transitions that are NOT in the map must be rejected:
     * transitionTo returns false and leaves state unchanged.
     */
    #[Test]
    public function transitions_matrix_rejects_sample_of_invalid_transitions(): void
    {
        $invalidSamples = [
            ['draft', 'completed'],          // drafts must go through approval flow
            ['draft', 'processing'],
            ['pending_approval', 'draft'],    // no return to Draft anywhere
            ['approved', 'completed'],       // must pass through Processing (or manager direct-complete)
            ['finalized', 'cancelled'],      // Finalized is terminal
            ['cancelled', 'pending_approval'], // Cancelled is terminal
            ['reversed', 'failed'],          // Reversed is terminal
            ['rejected', 'completed'],       // rejected can only go to Cancelled
            ['failed', 'reversed'],          // failed recovery cannot jump to Reversed
            ['processing', 'pending_approval'], // no backwards transition
        ];

        foreach ($invalidSamples as [$from, $to]) {
            $transaction = $this->createTransaction(TransactionStatus::from($from));
            $machine = new TransactionStateMachine($transaction, $this->auditService);

            $originalHistory = $transaction->transition_history ?? [];

            $result = $machine->transitionTo(
                TransactionStatus::from($to),
                ['reason' => "invalid {$from}->{$to}", 'user_id' => $this->user->id]
            );

            $this->assertFalse($result, "Transition {$from} -> {$to} is not in TRANSITIONS and must be rejected");
            $this->assertSame(
                $from,
                $transaction->fresh()->status->value,
                "Status must remain {$from} after rejected {$from} -> {$to}"
            );
            $this->assertSame(
                $originalHistory,
                $transaction->fresh()->transition_history ?? [],
                "No history entry may be written for rejected {$from} -> {$to}"
            );
        }
    }

    #[Test]
    public function retry_and_reprocess_are_rejected_from_non_failed_states(): void
    {
        $nonFailedStates = [
            TransactionStatus::Draft,
            TransactionStatus::PendingApproval,
            TransactionStatus::Approved,
            TransactionStatus::Processing,
            TransactionStatus::Completed,
            TransactionStatus::Finalized,
            TransactionStatus::Cancelled,
            TransactionStatus::Rejected,
        ];

        foreach ($nonFailedStates as $status) {
            $transaction = $this->createTransaction($status);
            $machine = new TransactionStateMachine($transaction);

            $this->assertFalse($machine->retry(), "retry() must reject from {$status->value}");
            $this->assertFalse($machine->reprocess(), "reprocess() must reject from {$status->value}");
            $this->assertSame(
                $status->value,
                $transaction->fresh()->status->value,
                "State must be untouched after guard rejection from {$status->value}"
            );
        }
    }

    #[Test]
    public function mark_as_dlq_throws_for_non_failed_states(): void
    {
        $transaction = $this->createTransaction(TransactionStatus::Processing);
        $stateMachine = new TransactionStateMachine($transaction);

        // Note: TransactionValidationException's constructor masks custom
        // messages behind a default ('Transaction validation failed'), so
        // only the exception type is asserted here.
        $this->expectException(TransactionValidationException::class);

        try {
            $stateMachine->markAsDlq('should throw');
        } catch (TransactionValidationException $e) {
            $this->assertSame(
                TransactionStatus::Processing,
                $transaction->fresh()->status,
                'markAsDlq guard must leave state unchanged'
            );

            throw $e;
        }
    }

    #[Test]
    public function retry_reprocess_and_mark_as_dlq_succeed_from_failed_state(): void
    {
        // retry(): Failed -> PendingApproval
        $transaction = $this->createTransaction(TransactionStatus::Failed);
        $machine = new TransactionStateMachine($transaction);

        $this->assertTrue($machine->retry());
        $this->assertSame(TransactionStatus::PendingApproval, $transaction->fresh()->status);

        // reprocess(): Failed -> Completed (automated recovery)
        $transaction = $this->createTransaction(TransactionStatus::Failed);
        $machine = new TransactionStateMachine($transaction);

        $this->assertTrue($machine->reprocess());
        $fresh = $transaction->fresh();
        $this->assertSame(TransactionStatus::Completed, $fresh->status);
        $history = $fresh->transition_history;
        $this->assertSame('Automated retry after failure', end($history)['reason']);

        // markAsDlq(): keeps Failed status, sets DLQ marker
        $transaction = $this->createTransaction(TransactionStatus::Failed);
        $machine = new TransactionStateMachine($transaction);

        $this->assertTrue($machine->markAsDlq('poison message'));
        $fresh = $transaction->fresh();
        $this->assertSame(TransactionStatus::Failed, $fresh->status);
        $this->assertTrue((bool) $fresh->is_dlq);
        $this->assertSame('poison message', $fresh->failure_reason);
        $history = $fresh->transition_history;
        $dlqEntry = end($history);
        $this->assertTrue((bool) ($dlqEntry['dlq_marker'] ?? false));
    }
}
