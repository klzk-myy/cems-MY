<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyCounterCloseTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $this->teller = User::create([
            'username' => 'teller'.substr(uniqid(), -6),
            'email' => 'teller-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    protected function createOpenSession(): CounterSession
    {
        return CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::Open,
        ]);
    }

    /** @test */
    public function it_can_initiate_emergency_close_via_api(): void
    {
        $session = $this->createOpenSession();

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Medical emergency - sudden illness',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('emergency_closures', [
            'counter_id' => $this->counter->id,
            'teller_id' => $this->teller->id,
        ]);

        $session->refresh();
        $this->assertEquals(CounterSessionStatus::EmergencyClosed, $session->status);
    }

    /** @test */
    public function it_enforces_4_hour_cooldown(): void
    {
        $session = $this->createOpenSession();

        EmergencyClosure::create([
            'counter_id' => $this->counter->id,
            'session_id' => $session->id,
            'teller_id' => $this->teller->id,
            'reason' => 'Previous emergency',
            'closed_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Another emergency',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_enforces_30_minute_session_minimum(): void
    {
        $session = CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(15),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::Open,
        ]);

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Emergency',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function manager_can_acknowledge_closure(): void
    {
        $session = $this->createOpenSession();

        $closure = EmergencyClosure::create([
            'counter_id' => $this->counter->id,
            'session_id' => $session->id,
            'teller_id' => $this->teller->id,
            'reason' => 'Medical emergency',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $response->assertStatus(200);

        $closure->refresh();
        $this->assertNotNull($closure->acknowledged_by);
        $this->assertNotNull($closure->acknowledged_at);
    }

    /** @test */
    public function non_manager_cannot_acknowledge(): void
    {
        $session = $this->createOpenSession();

        $closure = EmergencyClosure::create([
            'counter_id' => $this->counter->id,
            'session_id' => $session->id,
            'teller_id' => $this->teller->id,
            'reason' => 'Medical emergency',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->teller, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_variance_data(): void
    {
        $session = $this->createOpenSession();

        $closure = EmergencyClosure::create([
            'counter_id' => $this->counter->id,
            'session_id' => $session->id,
            'teller_id' => $this->teller->id,
            'reason' => 'Medical emergency',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/variance");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
