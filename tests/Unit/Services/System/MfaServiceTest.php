<?php

namespace Tests\Unit\Services\System;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use App\Services\System\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MfaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MfaService::class);
    }

    #[Test]
    public function generate_secret_returns_secret_and_otpauth_url(): void
    {
        $result = $this->service->generateSecret('user@example.com');

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('otpauth_url', $result);
        $this->assertStringStartsWith('otpauth://totp/', $result['otpauth_url']);
        $this->assertNotEmpty($result['secret']);
    }

    #[Test]
    public function verify_code_returns_true_for_code_generated_from_secret(): void
    {
        $secret = $this->service->generateSecret()['secret'];
        $code = $this->service->generateCode($secret);

        $this->assertTrue($this->service->verifyCode($secret, $code));
    }

    #[Test]
    public function verify_code_returns_false_for_wrong_code(): void
    {
        $secret = $this->service->generateSecret()['secret'];

        $this->assertFalse($this->service->verifyCode($secret, '000000'));
    }

    #[Test]
    public function same_totp_code_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret()['secret'];
        $this->service->storeSecret($user, $secret);

        $code = $this->service->generateCode($secret);

        $this->assertTrue($this->service->verifyUserCode($user, $code));

        // Same code inside its validity window must be rejected — the
        // timestep was consumed by the first verification.
        $this->assertFalse(
            $this->service->verifyUserCode($user->fresh(), $code),
            'Replaying the same TOTP within its window must fail'
        );

        // A code from the previous (still tolerated) window is also
        // rejected once a newer window was consumed.
        $period = (int) config('cems.mfa.period', 30);
        $olderCode = $this->service->generateCode($secret, time() - $period);
        if ($olderCode !== $code) {
            $this->assertFalse($this->service->verifyUserCode($user->fresh(), $olderCode));
        }
    }

    #[Test]
    public function recovery_code_cannot_be_double_spent(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateRecoveryCodes($user);

        // Simulate two concurrent submissions that both resolved the same
        // unused row: the atomic used=false consume lets only one through.
        $this->assertTrue($this->service->verifyRecoveryCode($user, $codes[0]));

        $row = MfaRecoveryCode::where('user_id', $user->id)->where('used', true)->sole();
        $staleConsume = MfaRecoveryCode::where('id', $row->id)
            ->where('used', false)
            ->update(['used' => true, 'used_at' => now()]);
        $this->assertSame(0, $staleConsume, 'The atomic consume must affect zero rows when already used');

        $this->assertFalse($this->service->verifyRecoveryCode($user, $codes[0]));
    }

    #[Test]
    public function generate_recovery_codes_returns_unique_codes(): void
    {
        $user = User::factory()->create();

        $codes = $this->service->generateRecoveryCodes($user);

        $this->assertCount(10, $codes);
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }

    #[Test]
    public function verify_recovery_code_returns_true_for_generated_code(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateRecoveryCodes($user);

        $this->assertTrue($this->service->verifyRecoveryCode($user, $codes[0]));
    }

    #[Test]
    public function verify_recovery_code_returns_false_for_invalid_code(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->verifyRecoveryCode($user, 'AAAA-BBBB'));
    }

    #[Test]
    public function recovery_code_is_single_use(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateRecoveryCodes($user);

        // First use succeeds...
        $this->assertTrue($this->service->verifyRecoveryCode($user, $codes[0]));

        // ...second use of the SAME code must fail because it was marked used.
        $this->assertFalse(
            $this->service->verifyRecoveryCode($user, $codes[0]),
            'A consumed recovery code must be rejected on second use'
        );

        // Remaining pool shrank by exactly one.
        $this->assertEquals(9, $this->service->getRemainingRecoveryCodesCount($user));
    }

    #[Test]
    public function unused_recovery_codes_are_not_affected_by_another_codes_use(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateRecoveryCodes($user);

        $this->assertTrue($this->service->verifyRecoveryCode($user, $codes[3]));

        // A different, still-unused code keeps working afterwards.
        $this->assertTrue($this->service->verifyRecoveryCode($user, $codes[7]));
        $this->assertEquals(8, $this->service->getRemainingRecoveryCodesCount($user));
    }
}
