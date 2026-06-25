<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchScopeAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function api_customer_index_is_scoped_to_user_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'BR-A'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'BR-B'.uniqid()]);

        $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);
        $userB = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchB->id]);

        // Create customers with transactions in different branches
        $customerInA = Customer::factory()->create();
        $customerInB = Customer::factory()->create();

        // Create transactions to link customers to branches
        Transaction::factory()->create([
            'customer_id' => $customerInA->id,
            'branch_id' => $branchA->id,
            'user_id' => $userA->id,
        ]);
        Transaction::factory()->create([
            'customer_id' => $customerInB->id,
            'branch_id' => $branchB->id,
            'user_id' => $userB->id,
        ]);

        $this->actingAs($userA);

        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();

        $customerIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($customerInB->id, $customerIds,
            'User from Branch A should not see Branch B customers');
    }
}
