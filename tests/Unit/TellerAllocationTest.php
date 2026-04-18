<?php

namespace Tests\Unit;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_teller_allocation(): void
    {
        $currency = Currency::factory()->create();
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $allocation = TellerAllocation::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'currency_code' => $currency->code,
            'session_date' => now()->toDateString(),
            'status' => TellerAllocationStatus::PENDING,
        ]);

        $this->assertDatabaseHas('teller_allocations', [
            'id' => $allocation->id,
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'currency_code' => $currency->code,
        ]);
    }

    public function test_has_pending_status_check(): void
    {
        $allocation = TellerAllocation::factory()->pending()->create();

        $this->assertTrue($allocation->isPending());
        $this->assertFalse($allocation->isApproved());
        $this->assertFalse($allocation->isActive());
        $this->assertFalse($allocation->isReturned());
    }

    public function test_has_available_uses_bccomp(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'current_balance' => '5000.0000',
        ]);

        $this->assertTrue($allocation->hasAvailable('5000.0000'));
        $this->assertTrue($allocation->hasAvailable('4999.9999'));
        $this->assertFalse($allocation->hasAvailable('5000.0001'));
    }

    public function test_deduct_reduces_balance(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'current_balance' => '10000.0000',
        ]);

        $allocation->deduct('2500.0000');

        $this->assertEquals('7500.0000', $allocation->current_balance);
    }

    public function test_add_increases_balance(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'current_balance' => '10000.0000',
        ]);

        $allocation->add('1500.0000');

        $this->assertEquals('11500.0000', $allocation->current_balance);
    }

    public function test_add_daily_used(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'daily_used_myr' => '0.0000',
        ]);

        $allocation->addDailyUsed('5000.0000');

        $this->assertEquals('5000.0000', $allocation->daily_used_myr);
    }

    public function test_has_daily_limit_remaining(): void
    {
        $allocation = TellerAllocation::factory()->create([
            'daily_limit_myr' => '50000.0000',
            'daily_used_myr' => '20000.0000',
        ]);

        $this->assertTrue($allocation->hasDailyLimitRemaining('30000.0000'));
        $this->assertTrue($allocation->hasDailyLimitRemaining('29999.9999'));
        $this->assertFalse($allocation->hasDailyLimitRemaining('30000.0001'));
    }

    public function test_has_daily_limit_remaining_returns_true_when_no_limit(): void
    {
        // daily_limit_myr is NOT NULL in the DB schema, so test the null-branch
        // via an unsaved model instance to avoid the constraint violation.
        $allocation = new TellerAllocation([
            'daily_limit_myr' => null,
            'daily_used_myr' => '0.0000',
        ]);

        $this->assertTrue($allocation->hasDailyLimitRemaining('999999.0000'));
    }

    public function test_approve_updates_status_and_amounts(): void
    {
        $approver = User::factory()->create();
        $allocation = TellerAllocation::factory()->pending()->create([
            'allocated_amount' => '0.0000',
            'current_balance' => '0.0000',
        ]);

        $allocation->approve($approver, '50000.0000', '100000.0000');

        $this->assertEquals(TellerAllocationStatus::APPROVED, $allocation->status);
        $this->assertEquals('50000.0000', $allocation->allocated_amount);
        $this->assertEquals('50000.0000', $allocation->current_balance);
        $this->assertEquals('100000.0000', $allocation->daily_limit_myr);
        $this->assertEquals($approver->id, $allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
    }

    public function test_activate_updates_status_and_timestamp(): void
    {
        $allocation = TellerAllocation::factory()->approved()->create();

        $allocation->activate();

        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
        $this->assertNotNull($allocation->opened_at);
    }

    public function test_return_to_pool(): void
    {
        $allocation = TellerAllocation::factory()->active()->create();

        $allocation->returnToPool();

        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->status);
        $this->assertNotNull($allocation->closed_at);
    }

    public function test_force_return(): void
    {
        $allocation = TellerAllocation::factory()->active()->create();

        $allocation->forceReturn();

        $this->assertEquals(TellerAllocationStatus::AUTO_RETURNED, $allocation->status);
        $this->assertNotNull($allocation->closed_at);
    }

    public function test_belongs_to_user(): void
    {
        $allocation = TellerAllocation::factory()->create();

        $this->assertInstanceOf(User::class, $allocation->user);
    }

    public function test_belongs_to_branch(): void
    {
        $allocation = TellerAllocation::factory()->create();

        $this->assertInstanceOf(Branch::class, $allocation->branch);
    }

    public function test_belongs_to_counter(): void
    {
        // Factory defaults counter_id to a new Counter; explicitly null it out.
        $allocation = TellerAllocation::factory()->create(['counter_id' => null]);

        $this->assertNull($allocation->counter);

        $counter = Counter::factory()->create();
        $allocationWithCounter = TellerAllocation::factory()->create(['counter_id' => $counter->id]);

        $this->assertInstanceOf(Counter::class, $allocationWithCounter->counter);
    }

    public function test_belongs_to_approver(): void
    {
        $allocation = TellerAllocation::factory()->create(['approved_by' => null]);

        $this->assertNull($allocation->approver);

        $approver = User::factory()->create();
        $allocationWithApprover = TellerAllocation::factory()->create(['approved_by' => $approver->id]);

        $this->assertInstanceOf(User::class, $allocationWithApprover->approver);
    }

    public function test_pending_state(): void
    {
        $allocation = TellerAllocation::factory()->pending()->create();

        $this->assertEquals(TellerAllocationStatus::PENDING, $allocation->status);
        $this->assertNull($allocation->approved_by);
        $this->assertNull($allocation->opened_at);
        $this->assertNull($allocation->closed_at);
    }

    public function test_active_state(): void
    {
        $allocation = TellerAllocation::factory()->active()->create();

        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
        $this->assertNotNull($allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
        $this->assertNotNull($allocation->opened_at);
        $this->assertNull($allocation->closed_at);
    }

    public function test_returned_state(): void
    {
        $allocation = TellerAllocation::factory()->returned()->create();

        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->status);
        $this->assertNotNull($allocation->approved_by);
        $this->assertNotNull($allocation->approved_at);
        $this->assertNotNull($allocation->opened_at);
        $this->assertNotNull($allocation->closed_at);
    }
}
