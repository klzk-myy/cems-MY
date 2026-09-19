<?php

namespace Tests\Feature;

use App\Enums\PoolRemittanceStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Models\PoolRemittance;
use App\Models\User;
use App\Services\Branch\PoolRemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Two-step pool remittance between a trading branch and head office.
 *
 * Initiation debits the sender's pool and parks the value in the 2300
 * inter-branch clearing account; acknowledgement credits the receiver's
 * pool and clears 2300. The clearing account must net to zero once the
 * cash lands — that is what keeps it honest.
 */
class PoolRemittanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $hq;

    private Branch $branch;

    private PoolRemittanceService $service;

    private User $manager;

    private User $hqUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hq = Branch::factory()->main()->create();
        $this->branch = Branch::factory()->create();
        $this->service = app(PoolRemittanceService::class);

        ChartOfAccount::firstOrCreate(
            ['account_code' => '2300'],
            ['account_name' => 'Inter-Branch Clearing', 'account_type' => 'Asset', 'is_active' => true]
        );

        $this->manager = $this->user(UserRole::Manager, $this->branch);
        $this->hqUser = $this->user(UserRole::Manager, $this->hq);
    }

    private function user(UserRole $role, Branch $branch): User
    {
        return User::factory()->create([
            'username' => strtolower($role->value).substr(uniqid(), -6),
            'email' => $role->value.'-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => $role,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function pool(Branch $branch, string $balance, string $currency = 'MYR'): BranchPool
    {
        return BranchPool::create([
            'branch_id' => $branch->id,
            'currency_code' => $currency,
            'available_balance' => $balance,
            'allocated_balance' => '0.0000',
        ]);
    }

    /**
     * Net posted amount on the clearing account across all journal lines.
     */
    private function clearingNet(): string
    {
        $net = 0.0;
        foreach (JournalLine::where('account_code', '2300')->get() as $line) {
            $net += (float) $line->debit - (float) $line->credit;
        }

        return number_format($net, 4, '.', '');
    }

    #[Test]
    public function branch_to_hq_remittance_parks_value_in_clearing_until_acknowledged(): void
    {
        $branchPool = $this->pool($this->branch, '1000.0000');

        $remittance = $this->service->initiate($this->branch, $this->hq, 'MYR', '400.00', $this->manager->id);

        $this->assertSame(PoolRemittanceStatus::Pending, $remittance->status);
        $this->assertStringStartsWith('REM-', $remittance->remittance_number);
        $this->assertSame('600.0000', $branchPool->fresh()->available_balance);
        $this->assertNotNull($remittance->out_journal_entry_id);

        // In transit: clearing holds the value on the sending branch's chain.
        $this->assertSame('400.0000', $this->clearingNet());

        $acknowledged = $this->service->acknowledge($remittance, $this->hqUser->id);

        $this->assertSame(PoolRemittanceStatus::Acknowledged, $acknowledged->status);
        $this->assertSame($this->hqUser->id, $acknowledged->acknowledged_by);
        $this->assertNotNull($acknowledged->acknowledged_at);
        $this->assertNotNull($acknowledged->ack_journal_entry_id);

        $hqPool = BranchPool::where('branch_id', $this->hq->id)->where('currency_code', 'MYR')->firstOrFail();
        $this->assertSame('400.0000', $hqPool->available_balance);

        // Cash landed — the clearing account nets back to zero.
        $this->assertSame('0.0000', $this->clearingNet());
    }

    #[Test]
    public function hq_to_branch_capital_remittance_credits_the_branch_on_acknowledgement(): void
    {
        $this->pool($this->hq, '5000.0000');
        $this->pool($this->branch, '100.0000');

        $remittance = $this->service->initiate($this->hq, $this->branch, 'MYR', '2500.00', $this->hqUser->id);
        $this->assertSame(PoolRemittanceStatus::Pending, $remittance->status);

        $this->service->acknowledge($remittance, $this->manager->id);

        $branchPool = BranchPool::where('branch_id', $this->branch->id)->where('currency_code', 'MYR')->firstOrFail();
        $this->assertSame('2600.0000', $branchPool->available_balance);
        $this->assertSame('0.0000', $this->clearingNet());
    }

    #[Test]
    public function initiate_rejects_insufficient_balance_without_touching_the_pool(): void
    {
        $pool = $this->pool($this->branch, '100.0000');

        try {
            $this->service->initiate($this->branch, $this->hq, 'MYR', '500.00', $this->manager->id);
            $this->fail('initiate should reject an amount above the available balance');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsString('Insufficient', $e->getMessage());
        }

        $this->assertSame('100.0000', $pool->fresh()->available_balance);
        $this->assertSame(0, PoolRemittance::count());
    }

    #[Test]
    public function remittance_is_only_between_a_branch_and_head_office(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->pool($this->branch, '1000.0000');

        $this->expectException(TransactionValidationException::class);
        $this->service->initiate($this->branch, $otherBranch, 'MYR', '100.00', $this->manager->id);
    }

    #[Test]
    public function foreign_currency_remittance_is_rejected(): void
    {
        $this->pool($this->branch, '1000.0000', 'USD');

        $this->expectException(TransactionValidationException::class);
        $this->service->initiate($this->branch, $this->hq, 'USD', '100.00', $this->manager->id);
    }

    #[Test]
    public function acknowledge_cannot_run_twice(): void
    {
        $this->pool($this->branch, '1000.0000');
        $remittance = $this->service->initiate($this->branch, $this->hq, 'MYR', '400.00', $this->manager->id);

        $this->service->acknowledge($remittance, $this->hqUser->id);

        try {
            $this->service->acknowledge($remittance, $this->hqUser->id);
            $this->fail('second acknowledge should be rejected');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsString('cannot be acknowledged', $e->getMessage());
        }

        $hqPool = BranchPool::where('branch_id', $this->hq->id)->where('currency_code', 'MYR')->firstOrFail();
        $this->assertSame('400.0000', $hqPool->available_balance);
    }

    #[Test]
    public function acknowledge_rejects_the_initiating_user(): void
    {
        $this->pool($this->branch, '1000.0000');
        $remittance = $this->service->initiate($this->branch, $this->hq, 'MYR', '400.00', $this->manager->id);

        try {
            $this->service->acknowledge($remittance, $this->manager->id);
            $this->fail('initiator acknowledging their own remittance should be rejected');
        } catch (TransactionValidationException $e) {
            $this->assertStringContainsString('initiated', $e->getMessage());
        }

        $this->assertSame(PoolRemittanceStatus::Pending, $remittance->fresh()->status);
        $this->assertNull(
            BranchPool::where('branch_id', $this->hq->id)->where('currency_code', 'MYR')->first()
        );
    }

    #[Test]
    public function cancel_returns_funds_to_the_sender_and_clears_the_in_transit_leg(): void
    {
        $pool = $this->pool($this->branch, '1000.0000');
        $remittance = $this->service->initiate($this->branch, $this->hq, 'MYR', '400.00', $this->manager->id);
        $this->assertSame('600.0000', $pool->fresh()->available_balance);

        $cancelled = $this->service->cancel($remittance, $this->manager->id, 'Sent in error');

        $this->assertSame(PoolRemittanceStatus::Cancelled, $cancelled->status);
        $this->assertSame('1000.0000', $pool->fresh()->available_balance);
        $this->assertSame('0.0000', $this->clearingNet());
        $this->assertNull(
            BranchPool::where('branch_id', $this->hq->id)->where('currency_code', 'MYR')->first()?->available_balance
        );
    }

    #[Test]
    public function remit_route_enforces_pool_ownership_and_acknowledge_enforces_receiving_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherManager = $this->user(UserRole::Manager, $otherBranch);
        $pool = $this->pool($this->branch, '1000.0000');

        // Another branch's manager cannot remit out of this pool.
        $this->actingAs($otherManager)
            ->post(route('branch-pools.remit', $pool), [
                'amount_myr' => '100.00',
                'to_branch_id' => $this->hq->id,
            ])
            ->assertForbidden();
        $this->flushSession();

        // The owning manager can.
        $this->actingAs($this->manager)
            ->post(route('branch-pools.remit', $pool), [
                'amount_myr' => '400.00',
                'to_branch_id' => $this->hq->id,
            ])
            ->assertRedirect();
        $this->flushSession();

        $remittance = PoolRemittance::sole();

        // The sending branch cannot acknowledge its own remittance.
        $this->actingAs($this->manager)
            ->post(route('branch-pools.remittances.acknowledge', $remittance))
            ->assertForbidden();
        $this->flushSession();

        // Nor can an unrelated branch.
        $this->actingAs($otherManager)
            ->post(route('branch-pools.remittances.acknowledge', $remittance))
            ->assertForbidden();
        $this->flushSession();

        // The receiving (HQ) side acknowledges.
        $this->actingAs($this->hqUser)
            ->post(route('branch-pools.remittances.acknowledge', $remittance))
            ->assertRedirect();

        $this->assertSame(PoolRemittanceStatus::Acknowledged, $remittance->fresh()->status);
    }

    #[Test]
    public function pool_show_lists_inbound_remittances_for_the_receiving_branch(): void
    {
        $hqPool = $this->pool($this->hq, '0.0000');
        $this->pool($this->branch, '1000.0000');
        $remittance = $this->service->initiate($this->branch, $this->hq, 'MYR', '400.00', $this->manager->id);

        $this->actingAs($this->hqUser)
            ->get(route('branch-pools.show', $hqPool))
            ->assertOk()
            ->assertSee($remittance->remittance_number)
            ->assertSee('Acknowledge Receipt');
    }
}
