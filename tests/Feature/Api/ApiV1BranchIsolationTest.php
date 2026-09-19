<?php

namespace Tests\Feature\Api;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiV1BranchIsolationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function manager_cannot_view_allocation_from_another_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'ISO-A'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'ISO-B'.uniqid()]);

        $managerB = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branchB->id,
        ]);

        $allocationInA = TellerAllocation::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        $response = $this->actingAs($managerB, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocationInA->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function manager_cannot_approve_allocation_from_another_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'ISO-C'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'ISO-D'.uniqid()]);

        $managerB = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branchB->id,
        ]);

        $allocationInA = TellerAllocation::factory()->pending()->create([
            'branch_id' => $branchA->id,
        ]);

        $response = $this->actingAs($managerB, 'sanctum')
            ->postJson("/api/v1/allocations/{$allocationInA->id}/approve", [
                'approved_quantity' => '50000',
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function admin_can_view_allocation_from_any_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'ISO-E'.uniqid()]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => $branchA->id,
        ]);

        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocation->id}")
            ->assertOk();
    }

    #[Test]
    public function teller_cannot_close_counter_from_another_branch(): void
    {
        $branchA = Branch::factory()->create(['code' => 'ISO-F'.uniqid()]);
        $branchB = Branch::factory()->create(['code' => 'ISO-G'.uniqid()]);

        $tellerB = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branchB->id,
        ]);

        $counterInA = Counter::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        $sessionUser = User::factory()->create(['branch_id' => $branchA->id]);
        CounterSession::factory()->create([
            'counter_id' => $counterInA->id,
            'user_id' => $sessionUser->id,
            'opened_by' => $sessionUser->id,
            'status' => CounterSessionStatus::Open->value,
        ]);

        $response = $this->actingAs($tellerB, 'sanctum')
            ->postJson("/api/v1/counters/{$counterInA->id}/close", [
                'closing_floats' => ['USD' => '1000'],
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function teller_can_close_counter_from_own_branch(): void
    {
        $branch = Branch::factory()->create(['code' => 'ISO-H'.uniqid()]);
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $counter = Counter::factory()->create([
            'branch_id' => $branch->id,
        ]);

        CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'opened_by' => $teller->id,
            'status' => CounterSessionStatus::Open->value,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->postJson("/api/v1/counters/{$counter->id}/close", [
                'closing_floats' => ['USD' => '1000'],
            ]);

        // 422 (variance/validation) is acceptable; a 403 means the branch
        // guard wrongly rejected a same-branch close.
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function wizard_session_is_bound_to_its_owner(): void
    {
        $tellerA = User::factory()->create(['role' => UserRole::Teller]);
        $tellerB = User::factory()->create(['role' => UserRole::Teller]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = Counter::factory()->create(['code' => 'WT1', 'id' => '999']);
        $customer = Customer::factory()->create(['risk_rating' => 'Low']);

        $session = $this->actingAs($tellerA)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => $customer->id,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ])
            ->assertStatus(200);

        $sessionId = $session->json('wizard_session_id');
        $this->assertNotNull($sessionId);

        // A different teller must not be able to inspect the session.
        $this->actingAs($tellerB)
            ->getJson("/api/v1/wizard/transactions/{$sessionId}/status")
            ->assertForbidden();

        // ... nor cancel it.
        $this->actingAs($tellerB)
            ->deleteJson("/api/v1/wizard/transactions/{$sessionId}")
            ->assertForbidden();

        // The owner can still read its status.
        $this->actingAs($tellerA)
            ->getJson("/api/v1/wizard/transactions/{$sessionId}/status")
            ->assertOk()
            ->assertJsonPath('status', 'active');
    }

    #[Test]
    public function wizard_step1_rejects_unknown_customer(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = Counter::factory()->create(['code' => 'WT2']);

        // The request rule requires an existing customer, so an unknown id is
        // rejected at validation (422) rather than reaching the controller.
        $this->actingAs($teller)
            ->postJson('/api/v1/wizard/transactions/step1', [
                'customer_id' => 999999,
                'type' => 'Buy',
                'currency_code' => 'USD',
                'quantity' => '100.00',
                'rate' => '4.50',
                'till_id' => $counter->code,
                'purpose' => 'Travel',
                'source_of_funds' => 'Salary',
            ])
            ->assertStatus(422);
    }
}
