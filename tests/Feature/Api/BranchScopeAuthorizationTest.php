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
    public function api_customer_index_is_company_wide_regardless_of_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'BR-A'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'BR-B'.uniqid()]);

        $userA = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchA->id]);
        $userB = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => $branchB->id]);

        // Customers are not branch-owned: a transaction at branch B does not
        // scope the customer away from branch A staff.
        $customerInA = Customer::factory()->create();
        $customerInB = Customer::factory()->create();

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
        $this->assertContains($customerInB->id, $customerIds,
            'Customers are company-wide: any role may view them regardless of branch');
    }

    #[Test]
    public function api_customer_index_denies_user_without_branch(): void
    {
        Customer::factory()->create(['full_name' => 'ShouldNotLeak-'.uniqid()]);

        $userNoBranch = User::factory()->create(['role' => UserRole::Teller, 'branch_id' => null]);

        // Deny-by-default branch scoping: a non-admin without a branch
        // assignment is rejected outright rather than served any data.
        $response = $this->actingAs($userNoBranch)->getJson('/api/v1/customers');

        $response->assertForbidden();
    }
}
