<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_branch_pool_allocations_do_not_go_negative(): void
    {
        $branch = Branch::factory()->create();
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '100.00',
            'allocated_balance' => '0.00',
        ]);

        $results = collect(range(1, 10))->map(function () use ($branch) {
            return new class($branch)
            {
                public function __construct(public Branch $branch) {}

                public function run(): bool
                {
                    return app(BranchPoolService::class)
                        ->allocateToTeller($this->branch, 'USD', '30.00');
                }
            };
        });

        // Only ~3 of 30-dollar allocations should succeed
        $this->assertSame(3, $results->filter(fn ($r) => $r->run())->count());
    }

    public function test_concurrent_get_or_create_branch_pool_does_not_duplicate(): void
    {
        $branch = Branch::factory()->create();

        $created = collect(range(1, 5))->map(fn () => app(BranchPoolService::class)->getOrCreateForBranch($branch, 'USD')
        );

        $this->assertSame(1, BranchPool::where('branch_id', $branch->id)
            ->where('currency_code', 'USD')
            ->count());
    }

    public function test_approve_allocation_cannot_be_approved_twice(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->for($branch)->create(['role' => UserRole::Manager]);
        $allocation = TellerAllocation::factory()->for($branch)->pending()->create([
            'currency_code' => 'USD',
        ]);
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '1000.00',
        ]);

        $service = app(TellerAllocationService::class);
        $service->approveAllocation($allocation, $manager, '500.00');

        $this->expectException(\RuntimeException::class);
        $service->approveAllocation($allocation, $manager, '500.00');
    }

    public function test_return_to_pool_is_idempotent_under_lock(): void
    {
        $branch = Branch::factory()->create();
        $allocation = TellerAllocation::factory()->for($branch)->active()->create([
            'currency_code' => 'USD',
            'current_balance' => '100.00',
        ]);
        BranchPool::factory()->for($branch)->create([
            'currency_code' => 'USD',
            'available_balance' => '0.00',
            'allocated_balance' => '100.00',
        ]);

        $service = app(TellerAllocationService::class);
        $service->returnToPool($allocation);

        $this->expectException(\RuntimeException::class);
        $service->returnToPool($allocation);
    }
}
