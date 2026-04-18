<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Services\BranchPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPoolServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BranchPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BranchPoolService;
    }

    public function test_get_or_create_for_branch(): void
    {
        $branch = Branch::factory()->create();

        $pool = $this->service->getOrCreateForBranch($branch, 'USD');

        $this->assertInstanceOf(BranchPool::class, $pool);
        $this->assertEquals($branch->id, $pool->branch_id);
        $this->assertEquals('USD', $pool->currency_code);
        $this->assertEquals('0.0000', $pool->available_balance);
        $this->assertEquals('0.0000', $pool->allocated_balance);
    }

    public function test_get_or_create_returns_existing_pool(): void
    {
        $branch = Branch::factory()->create();

        $pool1 = $this->service->getOrCreateForBranch($branch, 'USD');
        $pool2 = $this->service->getOrCreateForBranch($branch, 'USD');

        $this->assertSame($pool1->id, $pool2->id);
    }

    public function test_allocate_to_teller_reduces_available(): void
    {
        $branch = Branch::factory()->create();
        $pool = $this->service->getOrCreateForBranch($branch, 'USD');
        $pool->update(['available_balance' => '10000.0000']);

        $result = $this->service->allocateToTeller($branch, 'USD', '5000');

        $this->assertTrue($result);
        $pool->refresh();
        $this->assertEquals('5000.0000', $pool->available_balance);
        $this->assertEquals('5000.0000', $pool->allocated_balance);
    }

    public function test_allocate_fails_when_insufficient(): void
    {
        $branch = Branch::factory()->create();
        $pool = $this->service->getOrCreateForBranch($branch, 'USD');
        $pool->update(['available_balance' => '1000.0000']);

        $result = $this->service->allocateToTeller($branch, 'USD', '5000');

        $this->assertFalse($result);
        $pool->refresh();
        $this->assertEquals('1000.0000', $pool->available_balance);
        $this->assertEquals('0.0000', $pool->allocated_balance);
    }

    public function test_deallocate_returns_to_available(): void
    {
        $branch = Branch::factory()->create();
        $pool = $this->service->getOrCreateForBranch($branch, 'USD');
        $pool->update([
            'available_balance' => '5000.0000',
            'allocated_balance' => '5000.0000',
        ]);

        $result = $this->service->deallocateFromTeller($branch, 'USD', '3000');

        $this->assertTrue($result);
        $pool->refresh();
        $this->assertEquals('8000.0000', $pool->available_balance);
        $this->assertEquals('2000.0000', $pool->allocated_balance);
    }

    public function test_deallocate_fails_when_insufficient_allocated(): void
    {
        $branch = Branch::factory()->create();
        $pool = $this->service->getOrCreateForBranch($branch, 'USD');
        $pool->update([
            'available_balance' => '5000.0000',
            'allocated_balance' => '1000.0000',
        ]);

        $result = $this->service->deallocateFromTeller($branch, 'USD', '3000');

        $this->assertFalse($result);
        $pool->refresh();
        $this->assertEquals('5000.0000', $pool->available_balance);
        $this->assertEquals('1000.0000', $pool->allocated_balance);
    }
}
