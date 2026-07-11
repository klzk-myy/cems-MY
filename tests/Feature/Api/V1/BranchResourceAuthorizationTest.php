<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_any_branch_resource(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $branch->id);
    }

    #[Test]
    public function non_admin_user_can_access_their_own_branch_resource(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $branch->id);
    }

    #[Test]
    public function non_admin_user_receives_403_for_another_branch_resource(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $ownBranch->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/branches/{$otherBranch->id}");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Unauthorized access to this branch');
    }

    #[Test]
    public function missing_resource_returns_404_before_branch_check(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/branches/999999');

        $response->assertStatus(404);
    }
}
