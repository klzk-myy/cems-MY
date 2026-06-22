<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchClosingAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function teller_cannot_initiate_branch_closing(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller, 'sanctum')
            ->postJson("/api/v1/branches/{$branch->id}/closing/initiate");

        $response->assertForbidden();
    }

    #[Test]
    public function manager_can_initiate_branch_closing(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/branches/{$branch->id}/closing/initiate");

        $response->assertSuccessful();
    }
}
