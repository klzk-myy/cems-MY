<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_login_form_returns_200(): void
    {
        $this->get('/login')->assertStatus(200)->assertViewIs('auth.login');
    }

    #[Test]
    public function login_with_valid_credentials_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'username' => 'alice',
            'password_hash' => Hash::make('correct-password'),
            // Fresh rotation timestamp so login proceeds to the dashboard
            // instead of the forced password.change screen.
            'password_changed_at' => now(),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'username' => 'alice',
            'password' => 'correct-password',
            'ip' => '127.0.0.1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_redirects_to_forced_password_change_when_rotation_is_due(): void
    {
        // Expiry is disabled in the local env (SECURITY_PASSWORD_EXPIRY_DAYS=0);
        // pin the policy so this test is env-independent.
        config(['security.password_expiry_days' => 90]);

        $user = User::factory()->create([
            'username' => 'stale-password-user',
            'password_hash' => Hash::make('correct-password'),
            // No rotation timestamp means the BNM policy forces a change.
            'is_active' => true,
        ]);

        $this->post('/login', [
            'username' => 'stale-password-user',
            'password' => 'correct-password',
            'ip' => '127.0.0.1',
        ])->assertRedirect(route('password.change'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_with_wrong_password_returns_error(): void
    {
        User::factory()->create([
            'username' => 'bob',
            'password_hash' => Hash::make('wrong-password-x'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'username' => 'bob',
            'password' => 'not-the-right-one',
            'ip' => '127.0.0.1',
        ])->assertRedirect()
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    #[Test]
    public function login_with_inactive_user_returns_error(): void
    {
        User::factory()->create([
            'username' => 'inactive',
            'password_hash' => Hash::make('inactive-pass'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => 'inactive',
            'password' => 'inactive-pass',
            'ip' => '127.0.0.1',
        ])->assertRedirect()->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    #[Test]
    public function login_with_unknown_username_returns_error(): void
    {
        $this->post('/login', [
            'username' => 'nobody',
            'password' => 'unknown-user-pass',
            'ip' => '127.0.0.1',
        ])->assertRedirect()->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
