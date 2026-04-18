<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_teller_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Teller,
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'username' => 'nonexistent_user',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_password_is_hashed_in_database(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('plainpassword'),
        ]);

        $this->assertNotEquals('plainpassword', $user->password_hash);
        $this->assertTrue(password_verify('plainpassword', $user->password_hash));
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_dashboard_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_teller_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($user->role->canCreateTransaction());
        $this->assertFalse($user->role->canAccessAccounting());
        $this->assertFalse($user->role->canManageUsers());
    }

    public function test_manager_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        $this->assertTrue($user->role->canCreateTransaction());
        $this->assertTrue($user->role->canApproveLargeTransactions());
        $this->assertTrue($user->role->canAccessAccounting());
    }

    public function test_compliance_officer_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $this->assertTrue($user->role->canAccessCompliance());
        $this->assertFalse($user->role->canAccessAccounting());
    }

    public function test_admin_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($user->role->canManageUsers());
        $this->assertTrue($user->role->canAccessAll());
    }

    public function test_teller_cannot_access_accounting(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/accounting');

        $response->assertStatus(403);
    }

    public function test_manager_cannot_access_user_management(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/users');

        $response->assertStatus(403);
    }

    public function test_compliance_officer_cannot_access_accounting(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($compliance)->get('/accounting');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
    }

    public function test_manager_can_access_accounting(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/accounting');

        $response->assertStatus(200);
    }

    public function test_compliance_officer_can_access_compliance_portal(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($compliance)->get('/compliance');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_compliance_portal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/compliance');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_stock_cash(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/stock-cash');

        $response->assertStatus(200);
    }

    public function test_manager_can_access_stock_cash(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/stock-cash');

        $response->assertStatus(200);
    }

    public function test_teller_cannot_access_stock_cash(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/stock-cash');

        $response->assertStatus(403);
    }

    public function test_teller_cannot_access_compliance_portal(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/compliance');

        $response->assertStatus(403);
    }

    public function test_manager_cannot_access_compliance_portal(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/compliance');

        $response->assertStatus(403);
    }
}
