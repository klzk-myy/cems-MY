<?php

namespace Tests\Feature\Audit;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Services\Branch\BranchPoolService;
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
}
