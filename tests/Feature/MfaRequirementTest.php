<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller;

    protected User $manager;

    protected User $admin;

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

        // Create teller with MFA enabled
        $this->teller = User::factory()->create([
            'username' => 'teller'.substr(uniqid(), -6),
            'email' => 'teller-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'mfa_enabled' => true,
        ]);

        // Create manager with MFA enabled
        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'mfa_enabled' => true,
        ]);

        // Create admin with MFA enabled
        $this->admin = User::factory()->create([
            'username' => 'admin'.substr(uniqid(), -6),
            'email' => 'admin-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Admin,
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'mfa_enabled' => true,
        ]);
    }

    /**
     * Helper to set MFA session verification.
     */
    protected function withMfaVerified(): array
    {
        return [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
        ];
    }

    /**
     * Test that bulk customer import requires MFA verification.
     */
    public function test_bulk_imports_require_mfa(): void
    {
        // Without MFA verification - should get 403
        // Note: actingAs with web guard and NO withSession for MFA
        $response = $this->actingAs($this->admin)
            ->post('/api/v1/import/customers', [], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should not get 403 for MFA reason
        $response = $this->actingAs($this->admin)
            ->withSession($this->withMfaVerified())
            ->post('/api/v1/import/customers', [], ['Accept' => 'application/json']);

        // Should not be 403 for MFA reason (might be 422 for validation)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test that bulk transaction import requires MFA verification.
     */
    public function test_bulk_transaction_imports_require_mfa(): void
    {
        // Without MFA verification - should get 403
        $response = $this->actingAs($this->manager)
            ->post('/api/v1/import/transactions', [], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should not get 403 for MFA reason
        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaVerified())
            ->post('/api/v1/import/transactions', [], ['Accept' => 'application/json']);

        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test that counter approve-and-open requires MFA verification.
     */
    public function test_counter_approve_and_open_requires_mfa(): void
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

        CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'opened_by' => $teller->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Without MFA verification - should get 403
        $response = $this->actingAs($this->manager)
            ->post("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should succeed
        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaVerified())
            ->post("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ], ['Accept' => 'application/json']);

        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test that emergency close requires MFA verification.
     */
    public function test_emergency_close_requires_mfa(): void
    {
        CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $this->teller->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Without MFA verification - should get 403
        $response = $this->actingAs($this->manager)
            ->post("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should succeed
        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaVerified())
            ->post("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ], ['Accept' => 'application/json']);

        $this->assertEquals(201, $response->status());
    }

    /**
     * Test that emergency acknowledge requires MFA verification.
     */
    public function test_emergency_acknowledge_requires_mfa(): void
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

        // Without MFA verification - should get 403
        $response = $this->actingAs($this->manager)
            ->post("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge", [], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should succeed
        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaVerified())
            ->post("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge", [], ['Accept' => 'application/json']);

        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that handover acknowledge requires MFA verification.
     */
    public function test_handover_acknowledge_requires_mfa(): void
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

        // Without MFA verification - should get 403
        $response = $this->actingAs($this->manager)
            ->post("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should succeed
        $response = $this->actingAs($this->manager)
            ->withSession($this->withMfaVerified())
            ->post("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ], ['Accept' => 'application/json']);

        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that import status endpoint requires MFA verification.
     */
    public function test_import_status_requires_mfa(): void
    {
        // Without MFA verification - should get 403
        $response = $this->actingAs($this->admin)
            ->get('/api/v1/import/status/test-job-id', ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should not get 403 for MFA reason
        $response = $this->actingAs($this->admin)
            ->withSession($this->withMfaVerified())
            ->get('/api/v1/import/status/test-job-id', ['Accept' => 'application/json']);

        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test that import errors endpoint requires MFA verification.
     */
    public function test_import_errors_requires_mfa(): void
    {
        // Without MFA verification - should get 403
        $response = $this->actingAs($this->admin)
            ->get('/api/v1/import/errors/test-job-id', ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        // With MFA verification - should not get 403 for MFA reason
        $response = $this->actingAs($this->admin)
            ->withSession($this->withMfaVerified())
            ->get('/api/v1/import/errors/test-job-id', ['Accept' => 'application/json']);

        $this->assertNotEquals(403, $response->status());
    }
}
