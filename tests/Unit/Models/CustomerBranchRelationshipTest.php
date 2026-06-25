<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerBranchRelationshipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_has_branch_relationship(): void
    {
        $branch = Branch::factory()->create(['code' => 'TEST'.uniqid()]);
        $user = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branch->id]);
        $customer = Customer::factory()->create();

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);

        $this->assertNotNull($customer->branch);
        $this->assertEquals($branch->id, $customer->branch->id);
    }

    #[Test]
    public function customer_can_be_scoped_to_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'SCA'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'SCB'.uniqid()]);

        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);

        Transaction::factory()->create([
            'customer_id' => $customerA->id,
            'branch_id' => $branchA->id,
            'user_id' => $userA->id,
        ]);

        Transaction::factory()->create([
            'customer_id' => $customerB->id,
            'branch_id' => $branchB->id,
            'user_id' => $userA->id,
        ]);

        $scoped = Customer::forBranch($branchA->id)->get();
        $this->assertTrue($scoped->contains($customerA));
        $this->assertFalse($scoped->contains($customerB));
    }
}
