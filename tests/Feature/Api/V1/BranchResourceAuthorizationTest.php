<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
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

    #[Test]
    public function index_returns_branch_resources_with_pagination_meta(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);
        $branch = Branch::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/branches');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [[
                    'id', 'code', 'name', 'type', 'country',
                    'is_active', 'is_main', 'created_at', 'updated_at',
                ]],
                'links',
                'meta',
            ]);

        $this->assertContains($branch->id, array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function counters_endpoint_emits_full_counter_resource_fields(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAs($admin)->getJson("/api/v1/branches/{$branch->id}/counters");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $counter->id)
            ->assertJsonPath('data.0.branch_id', $branch->id)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'status', 'branch_id', 'created_at', 'updated_at']]]);
    }

    #[Test]
    public function users_endpoint_emits_full_user_resource_fields(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/branches/{$branch->id}/users");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $member->id)
            ->assertJsonPath('data.0.branch_id', $branch->id)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'username', 'email', 'role', 'branch_id',
                    'is_active', 'mfa_enabled', 'created_at', 'updated_at',
                ]],
            ]);
    }
}
