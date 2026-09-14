<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutOtherDevicesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $currentPassword = 'CurrentPass@123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'device-logout-user',
            'email' => 'device-logout@example.com',
            'password' => $this->currentPassword,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function wrong_current_password_is_rejected(): void
    {
        $originalHash = $this->user->password_hash;

        $this->actingAs($this->user)
            ->from(route('password.change'))
            ->post(route('profile.devices.logout-others'), [
                'current_password' => 'WrongPassword@999',
            ])
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors(['current_password']);

        $this->user->refresh();

        $this->assertSame($originalHash, $this->user->password_hash);
        $this->assertAuthenticatedAs($this->user);
    }

    #[Test]
    public function correct_current_password_logs_out_other_devices(): void
    {
        $originalHash = $this->user->password_hash;
        $originalChangedAt = $this->user->password_changed_at;

        $this->actingAs($this->user)
            ->from(route('password.change'))
            ->post(route('profile.devices.logout-others'), [
                'current_password' => $this->currentPassword,
            ])
            ->assertRedirect(route('password.change'))
            ->assertSessionHas('success', 'All other devices have been logged out.');

        // The password was re-hashed to invalidate other sessions; the new
        // hash must still verify the same plaintext password.
        $this->user->refresh();

        $this->assertNotSame($originalHash, $this->user->password_hash);
        $this->assertTrue(Hash::check($this->currentPassword, $this->user->password_hash));

        // The password itself is not changing — only the stored hash is
        // rotated to invalidate other sessions. The old hash must NOT be
        // archived to PasswordHistory (otherwise reusing the same password
        // later would be rejected by PasswordNotRecentlyUsed), and the
        // password_changed_at timestamp must NOT be refreshed.
        $this->assertDatabaseMissing('password_histories', [
            'user_id' => $this->user->id,
            'password' => $originalHash,
        ]);
        $this->assertEquals($originalChangedAt, $this->user->password_changed_at);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->post(route('profile.devices.logout-others'), [
            'current_password' => $this->currentPassword,
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
