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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Branch pool debit and teller-allocation adjustment actions (web): the
 * manager-side stock controls — debiting a pool, modifying an allocation's
 * quantity, and returning an active allocation to the pool.
 */
class BranchPoolAndAllocationActionsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]
        );

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
        ]);
    }

    #[Test]
    public function pool_debit_reduces_the_available_balance(): void
    {
        $pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '1000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->actingAs($this->manager)
            ->post(route('branch-pools.debit', $pool), ['quantity' => '400'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pool debited successfully.');

        $pool->refresh();
        $this->assertSame('600.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function pool_debit_over_the_balance_is_rejected_without_touching_the_pool(): void
    {
        $pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->actingAs($this->manager)
            ->post(route('branch-pools.debit', $pool), ['quantity' => '500'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $pool->refresh();
        $this->assertSame('100.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function pool_debit_is_scoped_to_the_managers_own_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $pool = BranchPool::factory()->create([
            'branch_id' => $otherBranch->id,
            'currency_code' => 'USD',
            'available_balance' => '1000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->actingAs($this->manager)
            ->post(route('branch-pools.debit', $pool), ['quantity' => '400'])
            ->assertForbidden();

        $pool->refresh();
        $this->assertSame('1000.0000', (string) $pool->available_balance);
    }

    #[Test]
    public function allocation_increase_draws_more_stock_from_the_pool(): void
    {
        $pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '10000.0000',
            'allocated_balance' => '0.0000',
        ]);
        $allocation = $this->allocation(TellerAllocationStatus::Approved, '5000.0000');

        $this->actingAs($this->manager)
            ->post(route('allocations.modify', $allocation), [
                'quantity' => '2000.0000',
                'direction' => 'increase',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Allocation increased by 2000.0000.');

        $allocation->refresh();
        $this->assertSame('7000.0000', (string) $allocation->allocated_quantity);
        $pool->refresh();
        $this->assertSame('2000.0000', (string) $pool->allocated_balance);
    }

    #[Test]
    public function allocation_decrease_beyond_the_allocated_amount_is_rejected(): void
    {
        $allocation = $this->allocation(TellerAllocationStatus::Approved, '5000.0000');

        $this->actingAs($this->manager)
            ->post(route('allocations.modify', $allocation), [
                'quantity' => '6000.0000',
                'direction' => 'decrease',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $allocation->refresh();
        $this->assertSame('5000.0000', (string) $allocation->allocated_quantity);
    }

    #[Test]
    public function returning_an_active_allocation_releases_the_pool_earmark(): void
    {
        $pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '5000.0000',
            'allocated_balance' => '5000.0000',
        ]);
        $allocation = $this->allocation(TellerAllocationStatus::Active, '5000.0000', '5000.0000');

        $this->actingAs($this->manager)
            ->post(route('allocations.return-to-pool', $allocation))
            ->assertRedirect()
            ->assertSessionHas('success', 'Allocation returned to the branch pool.');

        $allocation->refresh();
        $this->assertSame(TellerAllocationStatus::Returned, $allocation->status);

        $pool->refresh();
        $this->assertSame('10000.0000', (string) $pool->available_balance, 'The earmark must return to available');
        $this->assertSame('0.0000', (string) $pool->allocated_balance);
    }

    #[Test]
    public function a_non_active_allocation_cannot_be_returned_to_the_pool(): void
    {
        $allocation = $this->allocation(TellerAllocationStatus::Approved, '5000.0000');

        $this->actingAs($this->manager)
            ->post(route('allocations.return-to-pool', $allocation))
            ->assertRedirect()
            ->assertSessionHas('error', 'Allocation is not active.');

        $allocation->refresh();
        $this->assertSame(TellerAllocationStatus::Approved, $allocation->status);
    }

    private function allocation(
        TellerAllocationStatus $status,
        string $allocated,
        string $current = '0',
    ): TellerAllocation {
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $this->branch->id]);

        return TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $counter->id,
            'currency_code' => 'USD',
            'requested_quantity' => $allocated,
            'allocated_quantity' => $allocated,
            'current_quantity' => $current,
            'daily_used_myr' => '0',
            'status' => $status->value,
            'session_date' => now()->toDateString(),
        ]);
    }
}
