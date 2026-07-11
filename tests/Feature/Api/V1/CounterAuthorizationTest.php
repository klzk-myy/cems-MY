<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_existent_counter_returns_404(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($teller)
            ->postJson('/api/v1/counters/999999/opening-request', [
                'requested_floats' => ['USD' => '1000.00'],
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Counter not found');
    }

    #[Test]
    public function counter_outside_user_branch_returns_403(): void
    {
        $userBranch = Branch::factory()->create();
        $counterBranch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $userBranch->id,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $counterBranch->id]);

        $response = $this->actingAs($teller)
            ->postJson("/api/v1/counters/{$counter->id}/opening-request", [
                'requested_floats' => ['USD' => '1000.00'],
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_counter_in_any_branch(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'branch_id' => null,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/counters/{$counter->id}/opening-request", [
                'requested_floats' => ['USD' => '1000.00'],
            ]);

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function same_branch_user_can_access_counter(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAs($teller)
            ->postJson("/api/v1/counters/{$counter->id}/opening-request", [
                'requested_floats' => ['USD' => '1000.00'],
            ]);

        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
