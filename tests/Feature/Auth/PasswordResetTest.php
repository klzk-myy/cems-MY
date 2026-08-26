<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The application ships no password_reset_tokens migration, so the
        // database broker has nowhere to store tokens. Create the standard
        // Laravel shape here so this feature flow is exercisable.
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function ($table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    #[Test]
    public function user_can_request_reset_link_reset_password_and_login_with_new_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'reset-flow-user',
            'email' => 'reset-flow-user@example.com',
            'password' => 'OldPassword@123',
            'is_active' => true,
        ]);

        $oldHash = $user->password_hash;

        // 1. Request the reset link.
        $response = $this->post('/forgot-password', ['email' => $user->email]);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        // 2. Capture the raw token from the sent notification.
        $rawToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$rawToken) {
            $rawToken = $notification->token;

            return true;
        });
        $this->assertNotNull($rawToken, 'A reset token must be issued');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // 3. Complete the reset with the new password.
        $response = $this->post('/reset-password', [
            'token' => $rawToken,
            'email' => $user->email,
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        // 4. The stored hash must verify the NEW password and reject the old one.
        $user->refresh();
        $this->assertNotSame($oldHash, $user->password_hash);
        $this->assertTrue(
            Hash::check('NewPassword@456', $user->password_hash),
            'password_hash must be a verifiable hash of the new plaintext password'
        );
        $this->assertFalse(Hash::check('OldPassword@123', $user->password_hash));

        // 5. Login with the new password succeeds; the old password is rejected.
        $login = $this->post('/login', [
            'username' => $user->username,
            'password' => 'NewPassword@456',
        ]);
        $login->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $failedLogin = $this->from(route('login'))->post('/login', [
            'username' => $user->username,
            'password' => 'OldPassword@123',
        ]);
        $failedLogin->assertInvalid(['username']);
        $this->assertGuest();
    }

    #[Test]
    public function reset_rejects_invalid_token_and_mismatched_confirmation(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'bad-token-user@example.com',
            'password' => 'OldPassword@123',
        ]);

        // Mismatched confirmation is refused by validation before any change.
        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'NewPassword@456',
            'password_confirmation' => 'different-confirmation',
        ])->assertInvalid(['password']);

        // A well-formed but unknown token cannot change the password.
        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])->assertSessionHasErrors(['email']);

        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword@123', $user->password_hash));
        $this->assertGuest();
    }
}
