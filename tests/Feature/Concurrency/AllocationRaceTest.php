<?php

namespace Tests\Feature\Concurrency;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\Domain\InvalidAllocationStateException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\Transaction\TransactionErrorHandler;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for state transitions that ran on the caller's possibly
 * stale model instance:
 *
 * - activateAllocation() checked isApproved() and wrote Active with no
 *   transaction or lock — a racing cancelAllocation() (which releases the
 *   pool earmark and cancels) could be overwritten, resurrecting the
 *   allocation as Active with zero pool backing.
 * - rejectAllocation() checked isPending() outside the transaction and
 *   rejected the unlocked instance — a racing approveAllocation() (which
 *   drew pool funds into the earmark) could be silently flipped to
 *   Rejected while the earmark stayed allocated forever.
 * - TransactionErrorHandler::handleProcessingError() ran the state machine
 *   on an unlocked instance — a racing transition could be clobbered and
 *   its appended transition_history entries lost.
 *
 * SQLite has no row locks, so the races are simulated with stale instances —
 * see ConcurrentTestCase.
 */
class AllocationRaceTest extends ConcurrentTestCase
{
    protected Branch $branch;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);

        $this->branch = Branch::factory()->create();
        $this->actor = User::factory()->create(['branch_id' => $this->branch->id]);
    }

    #[Test]
    public function activate_on_stale_instance_after_cancel_throws(): void
    {
        $allocation = $this->approvedAllocation('500.0000');
        BranchPool::factory()->for($this->branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '0.0000',
            'allocated_balance' => '500.0000',
        ]);

        // Path B's view, taken while the row is still Approved.
        $stale = $this->staleCopy($allocation);

        // Path A: cancellation commits — earmark released back to the pool.
        app(TellerAllocationService::class)->cancelAllocation($allocation, $this->actor);
        $this->assertSame(TellerAllocationStatus::Cancelled, $allocation->fresh()->status);

        // Path B: stale Approved view must hit the locked re-check and throw
        // instead of resurrecting the cancelled allocation as Active with no
        // pool backing.
        try {
            app(TellerAllocationService::class)->activateAllocation($stale);
            $this->fail('activateAllocation did not throw on a cancelled allocation');
        } catch (InvalidAllocationStateException) {
        }

        $this->assertSame(TellerAllocationStatus::Cancelled, $allocation->fresh()->status);
    }

    #[Test]
    public function reject_on_stale_instance_after_approve_throws(): void
    {
        $allocation = TellerAllocation::factory()->pending()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'requested_quantity' => '500.0000',
        ]);
        BranchPool::factory()->for($this->branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '1000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $stale = $this->staleCopy($allocation);

        // Path A: approval commits — 500 moves available → allocated earmark.
        app(TellerAllocationService::class)->approveAllocation($allocation, $this->actor, '500.0000');
        $this->assertSame(TellerAllocationStatus::Approved, $allocation->fresh()->status);

        // Path B: stale Pending view must not flip the funded approval back
        // to Rejected, orphaning the pool earmark.
        try {
            app(TellerAllocationService::class)->rejectAllocation($stale, $this->actor);
            $this->fail('rejectAllocation did not throw on an approved allocation');
        } catch (InvalidAllocationStateException) {
        }

        $this->assertSame(TellerAllocationStatus::Approved, $allocation->fresh()->status);
        $pool = BranchPool::where('branch_id', $this->branch->id)->where('currency_code', 'USD')->firstOrFail();
        $this->assertEquals('500.0000', (string) $pool->allocated_balance);
    }

    #[Test]
    public function second_activate_on_stale_instance_throws(): void
    {
        $allocation = $this->approvedAllocation('500.0000');
        $stale = $this->staleCopy($allocation);

        app(TellerAllocationService::class)->activateAllocation($allocation);
        $this->assertSame(TellerAllocationStatus::Active, $allocation->fresh()->status);

        try {
            app(TellerAllocationService::class)->activateAllocation($stale);
            $this->fail('activateAllocation did not throw on an already-active allocation');
        } catch (InvalidAllocationStateException) {
        }

        $this->assertSame(TellerAllocationStatus::Active, $allocation->fresh()->status);
    }

    #[Test]
    public function second_reject_on_stale_instance_throws(): void
    {
        $allocation = TellerAllocation::factory()->pending()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
        ]);
        $stale = $this->staleCopy($allocation);

        app(TellerAllocationService::class)->rejectAllocation($allocation, $this->actor);
        $this->assertSame(TellerAllocationStatus::Rejected, $allocation->fresh()->status);

        try {
            app(TellerAllocationService::class)->rejectAllocation($stale, $this->actor);
            $this->fail('rejectAllocation did not throw on an already-rejected allocation');
        } catch (InvalidAllocationStateException) {
        }

        $this->assertSame(TellerAllocationStatus::Rejected, $allocation->fresh()->status);
    }

    #[Test]
    public function fail_on_stale_instance_does_not_clobber_racing_transition(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Completed->value,
        ]);

        // Path B's view: a Completed transaction that can legitimately be
        // marked Failed after a booking side-effect rollback.
        $stale = $this->staleCopy($transaction);

        // Path A: a reversal commits first — the transaction is now in a
        // terminal state from which Failed is unreachable.
        $transaction->forceFill(['status' => TransactionStatus::Reversed->value])->save();

        app(TransactionErrorHandler::class)->handleProcessingError(
            $stale,
            TransactionErrorHandler::ERROR_TYPE_PROCESSING,
            'simulated side-effect failure'
        );

        // The locked re-read must see Reversed and refuse the transition —
        // the broken path overwrote it with Failed on the stale instance.
        $this->assertSame(TransactionStatus::Reversed, $transaction->fresh()->status);
    }

    private function approvedAllocation(string $allocatedQuantity): TellerAllocation
    {
        return TellerAllocation::factory()->approved()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'allocated_quantity' => $allocatedQuantity,
            'current_quantity' => $allocatedQuantity,
            'requested_quantity' => $allocatedQuantity,
        ]);
    }
}
