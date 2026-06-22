<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function teller_cannot_view_other_allocation_details(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocation->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function manager_can_view_allocation_details(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/allocations/{$allocation->id}");

        $response->assertOk();
    }

    #[Test]
    public function teller_cannot_list_pending_allocations_for_branch(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/pending')
            ->assertForbidden();
    }

    #[Test]
    public function teller_cannot_list_active_allocations_for_branch(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->getJson('/api/v1/allocations/active')
            ->assertForbidden();
    }
}
