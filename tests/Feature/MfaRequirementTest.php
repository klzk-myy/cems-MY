<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\System\MfaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaRequirementTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller;

    protected User $manager;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Referer' => config('sanctum.stateful.0', config('app.url'))]);

        $this->branch = Branch::factory()->create();
        $this->counter = Counter::factory()->create(['branch_id' => $this->branch->id]);
        $this->teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => true,
        ]);
        $this->manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => true,
        ]);
        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'mfa_enabled' => true,
        ]);

        config(['cems.mfa.enabled' => true]);
        config(['cems.mfa.require_for_roles' => ['admin', 'manager']]);
    }

    protected function withMfaSession(): array
    {
        return [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
            '_session_created_at' => now()->timestamp,
        ];
    }

    #[Test]
    public function counter_approve_and_open_requires_mfa(): void
    {
        $teller = User::factory()->create([
            'username' => 'teller2'.substr(uniqid(), -6),
            'email' => 'teller2-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'mfa_enabled' => true,
        ]);

        // approve-and-open consumes a PENDING allocation per approved float
        // currency and draws from the branch pool; without both fixtures the
        // endpoint 500s for reasons unrelated to MFA. No pre-existing open
        // session may exist for the teller/counter or openSession() refuses.
        BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '10000.0000',
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::PENDING,
            'session_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ]);

        // Exact expected status: with MFA verified the request must succeed
        // (200 = counter opened), not merely "not 403".
        $response->assertStatus(200);
    }

    #[Test]
    public function emergency_close_requires_mfa(): void
    {
        CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Emergency close settles the teller's open USD allocation and returns
        // it to the branch pool; without those fixtures the endpoint 500s for
        // reasons unrelated to MFA.
        BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '10000.0000',
        ]);

        TellerAllocation::factory()->create([
            'user_id' => $this->teller->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::PENDING,
            'session_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ]);

        $this->assertEquals(201, $response->status());
    }

    #[Test]
    public function emergency_acknowledge_requires_mfa(): void
    {
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::EmergencyClosed,
        ]);

        $closure = EmergencyClosure::factory()->create([
            'counter_id' => $this->counter->id,
            'session_id' => $session->id,
            'teller_id' => $this->teller->id,
            'reason' => 'Test emergency',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function handover_acknowledge_requires_mfa(): void
    {
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::PendingHandover,
        ]);

        $teller2 = User::factory()->create([
            'username' => 'teller3'.substr(uniqid(), -6),
            'email' => 'teller3-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $handover = CounterHandover::factory()->create([
            'counter_session_id' => $session->id,
            'from_user_id' => $this->teller->id,
            'to_user_id' => $teller2->id,
            'supervisor_id' => $this->manager->id,
            'handover_time' => now(),
            'physical_count_verified' => true,
            'variance_myr' => '0.00',
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ]);

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function shipped_config_requires_mfa_for_every_staff_role(): void
    {
        // setUp overrides require_for_roles, so read the shipped file default
        // directly — a regression that drops a role (as happened to
        // 'accountant') fails here.
        $shipped = require config_path('cems.php');
        config(['cems.mfa.require_for_roles' => $shipped['mfa']['require_for_roles']]);

        $mfa = app(MfaService::class);

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->make(['role' => $role]);
            $this->assertTrue(
                $mfa->isMfaRequiredForRole($user),
                "MFA must be required for {$role->value}"
            );
        }
    }

    #[Test]
    public function unenrolled_user_past_grace_is_forced_to_mfa_setup_on_any_page(): void
    {
        // Manager is in require_for_roles (see setUp). created_at beyond the
        // 30-day grace means enrollment is overdue.
        $overdue = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => false,
            'created_at' => now()->subDays(31),
        ]);

        $response = $this->actingAs($overdue)->get('/dashboard');

        $response->assertRedirect(route('mfa.setup'));

        // The MFA flow itself stays reachable — otherwise the redirect loops.
        $this->actingAs($overdue)->get(route('mfa.setup'))->assertOk();
    }

    #[Test]
    public function unenrolled_user_within_grace_can_still_browse(): void
    {
        $recent = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'mfa_enabled' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($recent)->get('/dashboard')->assertOk();
    }
}
