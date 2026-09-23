<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeviceComputations;
use App\Models\User;
use App\Services\System\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MFA account-recovery surface: verifying with a one-time recovery code
 * (plus password) when the authenticator is unavailable, and removing a
 * trusted device.
 */
class MfaRecoveryAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MfaService $mfaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mfaService = app(MfaService::class);
        $this->user = User::factory()->create([
            'role' => UserRole::Teller,
            'password_hash' => Hash::make('correct-password'),
            'mfa_enabled' => true,
        ]);
        $this->mfaService->storeSecret($this->user, $this->mfaService->generateSecret('recovery@test.local')['secret']);
    }

    #[Test]
    public function recovery_code_with_password_grants_mfa_verification(): void
    {
        $codes = $this->mfaService->generateRecoveryCodes($this->user);

        $this->actingAs($this->user)
            ->post(route('mfa.recovery.verify'), [
                'recovery_code' => $codes[0],
                'password' => 'correct-password',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Access recovered successfully.');

        $this->assertTrue(session('mfa_verified'), 'The session must be marked MFA-verified');
    }

    #[Test]
    public function a_recovery_code_is_consumed_on_use(): void
    {
        $codes = $this->mfaService->generateRecoveryCodes($this->user);

        $this->actingAs($this->user)
            ->post(route('mfa.recovery.verify'), [
                'recovery_code' => $codes[0],
                'password' => 'correct-password',
            ])
            ->assertRedirect();

        // The same code must not work a second time.
        $this->actingAs($this->user)
            ->post(route('mfa.recovery.verify'), [
                'recovery_code' => $codes[0],
                'password' => 'correct-password',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('recovery_code');
    }

    #[Test]
    public function recovery_with_a_wrong_password_is_rejected(): void
    {
        $codes = $this->mfaService->generateRecoveryCodes($this->user);

        $this->actingAs($this->user)
            ->post(route('mfa.recovery.verify'), [
                'recovery_code' => $codes[0],
                'password' => 'wrong-password',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertNull(session('mfa_verified'));
    }

    #[Test]
    public function a_trusted_device_can_be_removed(): void
    {
        $this->mfaService->rememberDevice($this->user, 'Playwright test device', 30);
        $device = DeviceComputations::where('user_id', $this->user->id)->first();
        $this->assertNotNull($device);

        $this->actingAs($this->user)
            ->delete(route('mfa.trusted-devices.remove', $device->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('device_computations', ['id' => $device->id]);
    }

    #[Test]
    public function removing_another_users_device_is_rejected(): void
    {
        $this->mfaService->rememberDevice($this->user, 'Someone elses device', 30);
        $device = DeviceComputations::where('user_id', $this->user->id)->first();

        $intruder = User::factory()->create(['role' => UserRole::Teller]);

        $this->actingAs($intruder)
            ->delete(route('mfa.trusted-devices.remove', $device->id))
            ->assertRedirect()
            ->assertSessionHasErrors('device');

        $this->assertDatabaseHas('device_computations', ['id' => $device->id]);
    }
}
