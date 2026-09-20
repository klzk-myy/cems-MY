<?php

namespace Tests\Feature\Concurrency;

use App\Enums\StockReservationStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TransactionApprovalException;
use App\Models\Branch;
use App\Models\CurrencyPosition;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\StockReservation;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\System\MathService;
use App\Services\Transaction\StockTransferService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

/**
 * Stock-transfer lifecycle races (plan Phase 4, S1–S3).
 *
 * SQLite cannot interleave two connections, so races are simulated with stale
 * model instances: keep a copy loaded before a competing transition commits,
 * then run the same transition with it. On the fixed code the transition
 * re-reads the row under lockForUpdate and rejects; on the old code the stale
 * status sailed through and double-debited/double-credited positions.
 */
class StockTransferRaceTest extends ConcurrentTestCase
{
    protected Branch $branchA;

    protected Branch $branchB;

    protected User $managerA;

    protected User $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        // SealAuditHashJob runs afterCommit; on the sync driver a stale-gap
        // retry would throw through the business path. Faking records the
        // dispatch without executing it.
        Queue::fake();

        $this->branchA = Branch::factory()->create(['name' => 'Branch A', 'code' => 'BRA']);
        $this->branchB = Branch::factory()->create(['name' => 'Branch B', 'code' => 'BRB']);
        $this->managerA = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $this->branchA->id]);
        $this->managerB = User::factory()->create(['role' => UserRole::Manager, 'branch_id' => $this->branchB->id]);
    }

    private function serviceFor(User $user): StockTransferService
    {
        return app()->make(StockTransferService::class, ['requester' => $user]);
    }

    /**
     * Approved transfer: source branch holds 1000 USD at cost 4.20.
     */
    private function approvedTransfer(string $quantity = '1000'): StockTransfer
    {
        CurrencyPosition::create([
            'branch_id' => (string) $this->branchA->id,
            'currency_code' => 'USD',
            'quantity' => '2000',
            'average_cost' => '4.2000',
            'current_rate' => '4.3000',
        ]);

        $transfer = $this->serviceFor($this->managerA)->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => $quantity],
            ],
        ]);

        $this->serviceFor($this->managerB)->approveByBranchManager($transfer);

        return $transfer->fresh();
    }

    private function positionFor(Branch $branch): CurrencyPosition
    {
        return CurrencyPosition::where('branch_id', (string) $branch->id)
            ->where('currency_code', 'USD')
            ->firstOrFail();
    }

    #[Test]
    public function double_dispatch_does_not_double_debit(): void
    {
        $transfer = $this->approvedTransfer();

        // Path B's stale view: still BranchManagerApproved after A commits.
        $stale = $this->staleCopy($transfer);

        $this->serviceFor($this->managerA)->dispatch($transfer);

        $this->assertEquals('1000.0000', (string) $this->positionFor($this->branchA)->quantity);

        try {
            $this->serviceFor($this->managerA)->dispatch($stale);
            $this->fail('Second dispatch with a stale instance must be rejected');
        } catch (TransactionApprovalException) {
            // rejected by the locked status re-check
        }

        $this->assertEquals(
            '1000.0000',
            (string) $this->positionFor($this->branchA)->quantity,
            'Position must be debited exactly once'
        );
    }

    #[Test]
    public function receive_and_complete_cannot_double_credit(): void
    {
        $transfer = $this->approvedTransfer();

        $this->serviceFor($this->managerA)->dispatch($transfer);

        // Stale view held by a second destination-branch manager.
        $stale = $this->staleCopy($transfer->fresh());

        $this->serviceFor($this->managerB)->complete($transfer->fresh());

        try {
            $this->serviceFor($this->managerB)->complete($stale);
            $this->fail('Second complete with a stale instance must be rejected');
        } catch (TransactionApprovalException) {
            // rejected by the locked status re-check
        }

        $this->assertEquals(
            '1000.0000',
            (string) $this->positionFor($this->branchB)->quantity,
            'Destination must be credited exactly once'
        );
    }

    #[Test]
    public function dispatch_cannot_take_stock_reserved_for_pending_sell(): void
    {
        CurrencyPosition::create([
            'branch_id' => (string) $this->branchA->id,
            'currency_code' => 'USD',
            'quantity' => '2000',
            'average_cost' => '4.2000',
        ]);

        // 600 of the branch's 2000 USD is promised to a pending Sell —
        // dispatchable is 1400, so moving 1500 must fail even though the
        // raw position covers it.
        StockReservation::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->branchA->id,
            'quantity' => '600',
            'status' => StockReservationStatus::Pending,
            'expires_at' => now()->addHours(24),
            'created_by' => $this->managerA->id,
        ]);

        $transfer = $this->serviceFor($this->managerA)->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1500'],
            ],
        ]);
        $this->serviceFor($this->managerB)->approveByBranchManager($transfer);

        $this->expectException(InsufficientStockException::class);
        $this->serviceFor($this->managerA)->dispatch($transfer->fresh());
    }

    #[Test]
    public function transfer_gl_uses_position_cost_not_submitted_rate(): void
    {
        // average_cost 4.20 × 1000 = 4200 — a submitted rate of 99 must never
        // reach the journal legs.
        CurrencyPosition::create([
            'branch_id' => (string) $this->branchA->id,
            'currency_code' => 'USD',
            'quantity' => '2000',
            'average_cost' => '4.2000',
            'current_rate' => '4.3000',
        ]);

        $transfer = $this->serviceFor($this->managerA)->createRequest([
            'source_branch_name' => 'Branch A',
            'destination_branch_name' => 'Branch B',
            'items' => [
                ['currency_code' => 'USD', 'quantity' => '1000', 'rate' => '99.0000', 'value_myr' => '99000'],
            ],
        ]);

        $this->assertEquals('4.20000000', (string) $transfer->items->first()->rate);

        $this->serviceFor($this->managerB)->approveByBranchManager($transfer);
        $this->serviceFor($this->managerA)->dispatch($transfer->fresh());

        $entry = JournalEntry::where('reference_type', 'StockTransfer')
            ->where('reference_id', $transfer->id)
            ->sole();

        /** @var JournalLine $clearingDebit */
        $clearingDebit = $entry->lines()->where('account_code', '2300')->sole();
        $this->assertSame(0, (new MathService)->compare((string) $clearingDebit->debit, '4200'));
    }

    #[Test]
    public function dispatch_keeps_derived_position_columns_consistent(): void
    {
        $transfer = $this->approvedTransfer();

        $this->serviceFor($this->managerA)->dispatch($transfer);

        $position = $this->positionFor($this->branchA);

        // 2000 - 1000 = 1000 on hand; cost basis and market rate unchanged —
        // total_cost/current_value must describe the NEW quantity.
        $this->assertEquals('1000.0000', (string) $position->quantity);
        $this->assertEquals('4.20000000', (string) $position->average_cost);
        $this->assertEquals('4.30000000', (string) $position->current_rate);
        $this->assertEquals('4200.0000', (string) $position->total_cost);
        $this->assertEquals('4300.0000', (string) $position->current_value);
        $this->assertEquals('100.0000', (string) $position->unrealized_gain_loss);
    }

    #[Test]
    public function completion_lands_stock_at_cost_basis_on_destination(): void
    {
        $transfer = $this->approvedTransfer();

        $this->serviceFor($this->managerA)->dispatch($transfer);
        $this->serviceFor($this->managerB)->complete($transfer->fresh());

        $destination = $this->positionFor($this->branchB);

        $this->assertEquals('1000.0000', (string) $destination->quantity);
        // Fresh destination position adopts the transferred cost basis.
        $this->assertEquals('4.20000000', (string) $destination->average_cost);
        $this->assertEquals('4200.0000', (string) $destination->total_cost);
    }
}
