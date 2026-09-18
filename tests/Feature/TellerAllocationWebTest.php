<?php

namespace Tests\Feature;

use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationWebTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Branch $otherBranch;

    protected Counter $counter;

    protected User $teller;

    protected User $manager;

    protected User $otherBranchManager;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]
        );

        $this->branch = Branch::factory()->create();
        $this->otherBranch = Branch::factory()->create();

        BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->counter = Counter::factory()->create(['branch_id' => $this->branch->id]);

        $this->teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
        ]);

        $this->otherBranchManager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->otherBranch->id,
        ]);
    }

    #[Test]
    public function teller_can_view_my_allocations_index(): void
    {
        $this->actingAs($this->teller)
            ->get(route('my-allocations.index'))
            ->assertOk()
            ->assertSee('My Stock Allocations');
    }

    #[Test]
    public function teller_can_view_request_form(): void
    {
        $this->actingAs($this->teller)
            ->get(route('my-allocations.request'))
            ->assertOk()
            ->assertSee('Requested Amount');
    }

    #[Test]
    public function teller_can_submit_stock_request(): void
    {
        $this->setMfaVerification($this->teller);

        $this->actingAs($this->teller)
            ->post(route('my-allocations.request.store'), [
                'lines' => [
                    ['currency_code' => 'USD', 'amount' => '5000.0000'],
                ],
                'counter_id' => $this->counter->id,
            ])
            ->assertRedirect(route('my-allocations.index'));

        $allocation = TellerAllocation::where('user_id', $this->teller->id)->first();

        $this->assertNotNull($allocation);
        $this->assertEquals(TellerAllocationStatus::Pending, $allocation->status);
        $this->assertEquals('5000.0000', $allocation->requested_amount);
        $this->assertEquals($this->branch->id, $allocation->branch_id);
    }

    #[Test]
    public function manager_can_approve_pending_allocation_via_web(): void
    {
        $allocation = $this->pendingAllocation();

        $this->actingAs($this->manager)
            ->post(route('allocations.approve', $allocation->id), [
                'approved_amount' => '4000.0000',
                'daily_limit_myr' => '20000.00',
            ])
            ->assertRedirect();

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::Approved, $allocation->status);
        $this->assertEquals('4000.0000', $allocation->allocated_amount);
        $this->assertEquals($this->manager->id, $allocation->approved_by);
    }

    #[Test]
    public function manager_can_reject_pending_allocation_via_web(): void
    {
        $allocation = $this->pendingAllocation();

        $this->actingAs($this->manager)
            ->post(route('allocations.reject', $allocation->id), [
                'rejection_reason' => 'Insufficient branch stock',
            ])
            ->assertRedirect();

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::Rejected, $allocation->status);
        $this->assertEquals('Insufficient branch stock', $allocation->rejection_reason);
    }

    #[Test]
    public function manager_cannot_approve_allocation_from_other_branch(): void
    {
        $allocation = $this->pendingAllocation();

        $this->actingAs($this->otherBranchManager)
            ->post(route('allocations.approve', $allocation->id), [
                'approved_amount' => '4000.0000',
            ])
            ->assertForbidden();

        $this->assertEquals(TellerAllocationStatus::Pending, $allocation->fresh()->status);
    }

    #[Test]
    public function teller_can_accept_approved_allocation(): void
    {
        $allocation = $this->pendingAllocation();
        $allocation->update([
            'status' => TellerAllocationStatus::Approved->value,
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $this->setMfaVerification($this->teller);

        $this->actingAs($this->teller)
            ->post(route('my-allocations.accept', $allocation->id))
            ->assertRedirect();

        $this->assertEquals(TellerAllocationStatus::Active, $allocation->fresh()->status);
    }

    #[Test]
    public function teller_can_return_active_allocation(): void
    {
        $allocation = $this->pendingAllocation();
        $allocation->update([
            'status' => TellerAllocationStatus::Active->value,
            'allocated_amount' => '5000.0000',
            'current_balance' => '5000.0000',
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $this->setMfaVerification($this->teller);

        $this->actingAs($this->teller)
            ->post(route('my-allocations.return', $allocation->id))
            ->assertRedirect();

        $this->assertEquals(TellerAllocationStatus::Returned, $allocation->fresh()->status);
    }

    #[Test]
    public function teller_cannot_accept_another_tellers_allocation(): void
    {
        $otherTeller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        $allocation = TellerAllocation::create([
            'user_id' => $otherTeller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'requested_amount' => '5000.0000',
            'allocated_amount' => '5000.0000',
            'current_balance' => '0',
            'daily_used_myr' => '0',
            'status' => TellerAllocationStatus::Approved->value,
            'session_date' => now()->toDateString(),
        ]);

        $this->setMfaVerification($this->teller);

        $this->actingAs($this->teller)
            ->post(route('my-allocations.accept', $allocation->id))
            ->assertForbidden();
    }

    #[Test]
    public function non_teller_cannot_access_my_allocations(): void
    {
        $this->actingAs($this->manager)
            ->get(route('my-allocations.index'))
            ->assertForbidden();
    }

    private function pendingAllocation(): TellerAllocation
    {
        return TellerAllocation::create([
            'user_id' => $this->teller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'requested_amount' => '5000.0000',
            'allocated_amount' => '5000.0000',
            'current_balance' => '0',
            'daily_used_myr' => '0',
            'status' => TellerAllocationStatus::Pending->value,
            'session_date' => now()->toDateString(),
        ]);
    }
}
