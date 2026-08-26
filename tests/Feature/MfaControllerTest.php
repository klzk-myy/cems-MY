<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\System\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'username' => 'alice',
            'password_hash' => Hash::make('pass'),
            'is_active' => true,
            'mfa_enabled' => false,
        ]);
    }

    #[Test]
    public function setup_page_requires_authentication(): void
    {
        $this->get('/mfa/setup')->assertRedirect('/login');
    }

    #[Test]
    public function setup_page_loads_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get('/mfa/setup')
            ->assertStatus(200);
    }

    #[Test]
    public function verify_page_redirects_to_setup_when_mfa_not_enabled(): void
    {
        $this->actingAs($this->user)
            ->get('/mfa/verify')
            ->assertRedirect('/mfa/setup');
    }

    #[Test]
    public function recovery_page_loads_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get('/mfa/recovery')
            ->assertStatus(200);
    }

    #[Test]
    public function disable_rejects_wrong_password_without_consuming_code_attempt(): void
    {
        $service = app(MfaService::class);
        $secretData = $service->generateSecret('alice@test.com');
        $service->storeSecret($this->user, $secretData['secret']);
        $this->user->mfa_enabled = true;
        $this->user->save();

        $code = $service->generateCode($secretData['secret']);

        $response = $this->actingAs($this->user)
            ->from('/mfa/trusted-devices')
            ->post('/mfa/disable', [
                'current_password' => 'wrong-password',
                'code' => $code,
            ]);

        $response->assertRedirect('/mfa/trusted-devices');
        $response->assertSessionHasErrors('current_password');

        // Wrong password must not count as a code attempt (lockout counter).
        $this->assertSame(0, $service->failedAttemptCount($this->user));

        // MFA must remain enabled.
        $this->assertTrue($this->user->fresh()->mfa_enabled);

        // The valid code is still usable afterwards with the right password.
        $response = $this->actingAs($this->user)
            ->post('/mfa/disable', [
                'current_password' => 'pass',
                'code' => $code,
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('status');

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->mfa_enabled);
        $this->assertNull($fresh->mfa_secret);
    }

    #[Test]
    public function disable_requires_current_password_field(): void
    {
        $service = app(MfaService::class);
        $secretData = $service->generateSecret('alice@test.com');
        $service->storeSecret($this->user, $secretData['secret']);
        $this->user->mfa_enabled = true;
        $this->user->save();

        $code = $service->generateCode($secretData['secret']);

        $response = $this->actingAs($this->user)
            ->post('/mfa/disable', [
                'code' => $code,
            ]);

        $response->assertSessionHasErrors('current_password');

        $this->assertTrue($this->user->fresh()->mfa_enabled);
        $this->assertSame(0, $service->failedAttemptCount($this->user));
    }
}
