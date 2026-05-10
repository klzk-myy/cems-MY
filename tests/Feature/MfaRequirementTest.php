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
use Illuminate\Http\UploadedFile;
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

    public function test_bulk_imports_require_mfa(): void
    {
        $csv = UploadedFile::fake()->create('customers.csv', 100, 'text/csv');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_bulk_transaction_imports_require_mfa(): void
    {
        $csv = UploadedFile::fake()->create('transactions.csv', 100, 'text/csv');

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/import/transactions', ['file' => $csv]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson('/api/v1/import/transactions', ['file' => $csv]);

        $this->assertNotEquals(403, $response->status());
    }

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

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/approve-and-open", [
                'teller_id' => $teller->id,
                'approved_floats' => ['USD' => '5000.00'],
            ]);

        $this->assertNotEquals(403, $response->status());
    }

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

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency-close", [
                'reason' => 'Test emergency',
            ]);

        $this->assertEquals(201, $response->status());
    }

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

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/emergency/{$closure->id}/acknowledge");

        $this->assertEquals(200, $response->status());
    }

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

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->withSession($this->withMfaSession())
            ->postJson("/api/v1/counters/{$this->counter->id}/handover/{$handover->id}/acknowledge", [
                'verified' => true,
                'notes' => 'Test',
            ]);

        $this->assertEquals(200, $response->status());
    }

    public function test_import_status_requires_mfa(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/import/status/test-job-id');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($this->withMfaSession())
            ->getJson('/api/v1/import/status/test-job-id');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_mfa_reverification_does_not_extend_session_beyond_limit(): void
    {
        $csv = UploadedFile::fake()->create('customers.csv', 100, 'text/csv');
        $sessionData = [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
            '_session_created_at' => now()->timestamp - (480 * 60),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($sessionData)
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Session expired, please re-authenticate',
            ]);
    }

    public function test_mfa_verification_works_within_session_lifetime(): void
    {
        $csv = UploadedFile::fake()->create('customers.csv', 100, 'text/csv');
        $sessionData = [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
            '_session_created_at' => now()->timestamp - (60 * 60),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($sessionData)
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_trusted_device_reverification_respects_session_lifetime(): void
    {
        $csv = UploadedFile::fake()->create('customers.csv', 100, 'text/csv');
        $sessionData = [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp,
            'mfa_trusted_device_verified' => true,
            '_session_created_at' => now()->timestamp - (7 * 60 * 60),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($sessionData)
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_expired_mfa_verification_requires_re_verification(): void
    {
        $csv = UploadedFile::fake()->create('customers.csv', 100, 'text/csv');
        $sessionData = [
            'mfa_verified' => true,
            'mfa_verified_at' => now()->timestamp - (16 * 60),
            '_session_created_at' => now()->timestamp - (60),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($sessionData)
            ->postJson('/api/v1/import/customers', ['file' => $csv]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);
    }

    public function test_import_errors_requires_mfa(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/import/errors/test-job-id');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'MFA verification required',
            ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession($this->withMfaSession())
            ->getJson('/api/v1/import/errors/test-job-id');

        $this->assertNotEquals(403, $response->status());
    }
}
