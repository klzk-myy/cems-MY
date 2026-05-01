<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerRoleCheckTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

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
    }

    /** @test */
    public function teller_role_check_only_passes_for_tellers(): void
    {
        // Create a teller user
        $teller = User::factory()->create([
            'username' => 'teller'.substr(uniqid(), -6),
            'email' => 'teller-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create a manager user
        $manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Teller CAN access teller-only routes
        // We get 422 (unprocessable entity/validation) rather than 403, which means the middleware passed
        $response = $this->actingAs($teller, 'sanctum')
            ->postJson('/api/v1/wizard/transactions/step1', [
                'currency_code' => 'USD',
                'transaction_type' => 'buy',
            ]);

        // Should NOT be 403 - teller is allowed through
        $this->assertNotEquals(403, $response->status());

        // Manager should NOT be able to access teller-only routes
        // If bug exists, manager gets past middleware (422 instead of 403)
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/wizard/transactions/step1', [
                'currency_code' => 'USD',
                'transaction_type' => 'buy',
            ]);

        // After fix: Manager should get 403 Forbidden
        // Before fix: Manager gets 422 (passes through middleware)
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_cannot_access_teller_only_routes(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'username' => 'admin'.substr(uniqid(), -6),
            'email' => 'admin-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Admin,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Admin should NOT be able to access teller-only routes
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/wizard/transactions/step1', [
                'currency_code' => 'USD',
                'transaction_type' => 'buy',
            ]);

        // Admin should get 403 Forbidden when accessing teller-only route
        $response->assertStatus(403);
    }

    /** @test */
    public function compliance_officer_cannot_access_teller_only_routes(): void
    {
        // Create a compliance officer
        $complianceOfficer = User::factory()->create([
            'username' => 'compliance'.substr(uniqid(), -6),
            'email' => 'compliance-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::ComplianceOfficer,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Compliance Officer should NOT be able to access teller-only routes
        $response = $this->actingAs($complianceOfficer, 'sanctum')
            ->postJson('/api/v1/wizard/transactions/step1', [
                'currency_code' => 'USD',
                'transaction_type' => 'buy',
            ]);

        // Compliance Officer should get 403 Forbidden when accessing teller-only route
        $response->assertStatus(403);
    }
}
