<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function teller_can_login_with_valid_credentials(): void
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

    #[Test]
    public function login_fails_with_invalid_password(): void
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

    #[Test]
    public function login_fails_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'username' => 'nonexistent_user',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    #[Test]
    public function login_throttle_is_keyed_per_username(): void
    {
        $attempts = (int) config('security.rate_limits.login.attempts', 5);

        // Exhaust the throttle bucket for one username from this IP.
        for ($i = 0; $i < $attempts; $i++) {
            $this->post('/login', [
                'username' => 'throttled_user',
                'password' => 'wrongpassword',
            ]);
        }

        // A different username from the same IP must get its own bucket —
        // the limiter keys on the submitted username, not a shared field.
        $this->post('/login', [
            'username' => 'other_user',
            'password' => 'wrongpassword',
        ])->assertSessionHasErrors('username')
            ->assertStatus(302);

        // The exhausted username hits the cap on the next attempt.
        $this->post('/login', [
            'username' => 'throttled_user',
            'password' => 'wrongpassword',
        ])->assertStatus(429);
    }

    #[Test]
    public function inactive_user_cannot_login(): void
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

    #[Test]
    public function password_is_hashed_in_database(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('plainpassword'),
        ]);

        $this->assertNotEquals('plainpassword', $user->password_hash);
        $this->assertTrue(password_verify('plainpassword', $user->password_hash));
    }

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function authenticated_user_can_logout_via_get(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function dashboard_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    #[Test]
    public function teller_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);

        $this->assertTrue($user->role->canCreateTransaction());
        $this->assertFalse($user->role->canAccessAccounting());
        $this->assertFalse($user->role->canManageUsers());
    }

    #[Test]
    public function manager_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        // Only tellers create transactions; managers manage own branch users/settings.
        $this->assertFalse($user->role->canCreateTransaction());
        $this->assertFalse($user->role->canApproveTransactions());
        $this->assertFalse($user->role->canApproveLargeTransactions());
        $this->assertTrue($user->role->canAccessAccounting());
        $this->assertTrue($user->role->canManageUsers());
        $this->assertTrue($user->role->canManageSettings());
        $this->assertTrue($user->role->canTransferTellerStock());
    }

    #[Test]
    public function compliance_officer_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $this->assertTrue($user->role->canAccessCompliance());
        $this->assertTrue($user->role->canApproveLargeTransactions());
        $this->assertTrue($user->role->canReverseTransaction());
        $this->assertFalse($user->role->canAccessAccounting());
    }

    #[Test]
    public function accountant_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Accountant]);

        $this->assertTrue($user->role->canAccessAccounting());
        $this->assertTrue($user->role->canViewReports());
        $this->assertFalse($user->role->canCreateTransaction());
        $this->assertFalse($user->role->canApproveTransactions());
    }

    #[Test]
    public function admin_has_correct_role_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($user->role->canManageUsers());
        $this->assertTrue($user->role->canAccessAll());
    }

    #[Test]
    public function teller_cannot_access_accounting(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/accounting');

        $response->assertStatus(403);
    }

    #[Test]
    public function manager_can_access_user_management(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);

        $response = $this->actingAs($manager)->get('/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function compliance_officer_cannot_access_accounting(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($compliance)->get('/accounting');

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_user_management(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function manager_can_access_accounting(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/accounting');

        $response->assertStatus(200);
    }

    #[Test]
    public function compliance_officer_can_access_compliance_portal(): void
    {
        $compliance = User::factory()->create(['role' => UserRole::ComplianceOfficer]);

        $response = $this->actingAs($compliance)->get('/compliance');

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_compliance_portal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/compliance');

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_stock_cash(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/stock-cash');

        $response->assertStatus(200);
    }

    #[Test]
    public function manager_can_access_stock_cash(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/stock-cash');

        $response->assertStatus(200);
    }

    #[Test]
    public function teller_cannot_access_stock_cash(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/stock-cash');

        $response->assertStatus(403);
    }

    #[Test]
    public function teller_cannot_access_compliance_portal(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $response = $this->actingAs($teller)->get('/compliance');

        $response->assertStatus(403);
    }

    #[Test]
    public function manager_cannot_access_compliance_portal(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)->get('/compliance');

        $response->assertStatus(403);
    }
}
