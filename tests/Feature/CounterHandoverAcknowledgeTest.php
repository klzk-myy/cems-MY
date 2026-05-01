<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterHandoverAcknowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller1;

    protected User $teller2;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $this->teller1 = User::factory()->create([
            'username' => 'teller1'.substr(uniqid(), -6),
            'email' => 'teller1-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->teller2 = User::factory()->create([
            'username' => 'teller2'.substr(uniqid(), -6),
            'email' => 'teller2-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    protected function createPendingHandover(): array
    {
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        $handover = CounterHandover::factory()->create([
            'counter_session_id' => $session->id,
            'from_user_id' => $this->teller1->id,
            'to_user_id' => $this->teller2->id,
            'supervisor_id' => $this->manager->id,
            'handover_time' => now(),
            'physical_count_verified' => true,
            'variance_myr' => '0.00',
        ]);

        $session->update(['status' => CounterSessionStatus::PendingHandover]);

        return ['session' => $session, 'handover' => $handover];
    }

    /** @test */
    public function incoming_teller_can_acknowledge_handover(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        $response = $this->actingAs($this->teller2, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Count verified and correct',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Handover acknowledged successfully',
            ]);

        $handover->refresh();
        $this->assertNotNull($handover->acknowledged_at);

        $result['session']->refresh();
        $this->assertEquals(CounterSessionStatus::Open, $result['session']->status);
    }

    /** @test */
    public function wrong_user_cannot_acknowledge_handover(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        $response = $this->actingAs($this->teller1, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function cannot_acknowledge_non_pending_handover(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        $result['session']->update(['status' => CounterSessionStatus::HandedOver]);

        $response = $this->actingAs($this->teller2, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function web_route_teller_can_acknowledge_handover(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        $response = $this->actingAs($this->teller2)
            ->post("/counters/{$this->counter->code}/handover/acknowledge", [
                'verified' => true,
                'notes' => 'Verified via web',
            ]);

        $response->assertRedirect();

        $handover->refresh();
        $this->assertNotNull($handover->acknowledged_at);
    }

    /** @test */
    public function web_route_show_acknowledge_form(): void
    {
        $result = $this->createPendingHandover();

        $response = $this->actingAs($this->teller2)
            ->get("/counters/{$this->counter->code}/handover/acknowledge");

        if ($response->status() === 500) {
            $content = $response->getContent();
            echo "\n500 Response: ".substr($content, 0, 500)."\n";
        }

        $response->assertStatus(200);
    }

    /** @test */
    public function cannot_acknowledge_twice(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        $this->actingAs($this->teller2, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
            ]);

        $response = $this->actingAs($this->teller2, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_handover_acknowledge_requires_manager_role(): void
    {
        $result = $this->createPendingHandover();
        $handover = $result['handover'];

        // Teller should get 403
        $response = $this->actingAs($this->teller1, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
            ]);

        $response->assertStatus(403);

        // Manager should get 200
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Manager verified handover',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Handover acknowledged successfully',
            ]);

        $handover->refresh();
        $this->assertNotNull($handover->acknowledged_at);
    }
}
