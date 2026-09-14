<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * password.confirm gate on destructive routes: unconfirmed sessions are
 * redirected to /confirm-password, confirmed sessions pass through.
 */
class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirm_password_page_requires_auth(): void
    {
        $this->get(route('password.confirm'))->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_can_view_confirm_password_page(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('Confirm password');
    }

    #[Test]
    public function correct_password_marks_session_confirmed(): void
    {
        $user = User::factory()->admin()->create(['password' => 'CurrentPass123!']);

        $response = $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'CurrentPass123!',
        ]);

        $response->assertRedirect();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    #[Test]
    public function wrong_password_is_rejected(): void
    {
        $user = User::factory()->admin()->create(['password' => 'CurrentPass123!']);

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'WrongPass123!'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session('auth.password_confirmed_at'));
    }

    #[Test]
    public function destructive_route_redirects_to_confirm_when_unconfirmed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->setMfaVerification($admin);

        $this->post(route('users.store'), [])
            ->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function destructive_route_passes_through_when_confirmed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->setMfaVerification($admin);

        // Empty payload fails validation — reaching validation proves the
        // password.confirm middleware let the request through.
        $this->withSession($this->passwordConfirmedSession())
            ->post(route('users.store'), [])
            ->assertSessionHasErrors();
    }
}
