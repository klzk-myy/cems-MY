<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\System\MfaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Branch $branch;

    private MfaService $mfaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mfaService = app(MfaService::class);

        $this->branch = Branch::factory()->create();

        $this->user = User::factory()->create([
            'username' => 'api-mfa-'.substr(uniqid(), -6),
            'email' => 'api-mfa-'.uniqid().'@test.com',
            'password_hash' => Hash::make('pass'),
            // Teller: the protected endpoint below creates transactions,
            // which is teller-only under the role model.
            'role' => 'teller',
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'mfa_enabled' => false,
        ]);

        $this->actingAs($this->user);

        config(['cems.mfa.enabled' => true]);
        config(['cems.mfa.require_for_roles' => ['teller']]);

        // The routes sit behind throttle:sensitive (3/min by default); these
        // tests exercise full lifecycle flows with many requests.
        config(['security.rate_limits.sensitive.attempts' => 100]);
    }

    #[Test]
    public function full_enroll_verify_and_protected_access_cycle(): void
    {
        // Wrong password must not even start enrollment.
        $this->postJson('/api/v1/mfa/enroll', ['current_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $enroll = $this->postJson('/api/v1/mfa/enroll', ['current_password' => 'pass']);

        $enroll->assertStatus(200)
            ->assertJsonPath('success', true);

        $secret = $enroll->json('data.secret');
        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('otpauth://totp/', $enroll->json('data.otpauth_url'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $enroll->json('data.qr_code_data_uri'));

        // Invalid code does not activate MFA.
        $this->postJson('/api/v1/mfa/verify', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertFalse($this->user->fresh()->mfa_enabled);

        $code = $this->mfaService->generateCode($secret);

        $verify = $this->postJson('/api/v1/mfa/verify', ['code' => $code]);

        $verify->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(10, $verify->json('data.recovery_codes'));
        $this->assertTrue($this->user->fresh()->mfa_enabled);

        // A mfa.verified-protected endpoint now answers the request when the
        // client presents its trusted-device token (the stateless equivalent
        // of the web MFA session).
        [$endpoint, $payload] = $this->protectedEndpoint();
        $deviceToken = $this->mfaService->rememberDevice($this->user, 'test-agent', 1);

        $this->postJson($endpoint, $payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'MFA verification required']);

        // withCredentials is required for JSON test requests to carry cookies,
        // and the stateful (Referer-matched) pipeline is what decrypts the
        // cookie exactly as it would for a first-party SPA client.
        // withCredentials is required for JSON test requests to carry cookies,
        // and the stateful (Referer-matched) pipeline is what decrypts the
        // cookie exactly as it would for a first-party SPA client.
        $this->withCredentials()
            ->withHeaders(['Referer' => config('sanctum.stateful.0', config('app.url'))])
            ->withCookie(MfaService::DEVICE_COOKIE_NAME, $deviceToken)
            ->postJson($endpoint, $payload)
            ->assertStatus(201);
    }

    #[Test]
    public function disable_requires_correct_password_and_code(): void
    {
        $secretData = $this->enableMfaForUser();

        $code = $this->mfaService->generateCode($secretData['secret']);

        // Missing password.
        $this->postJson('/api/v1/mfa/disable', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        // Wrong password: rejected without consuming the code attempt.
        $this->postJson('/api/v1/mfa/disable', [
            'current_password' => 'wrong',
            'code' => $code,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, $this->mfaService->failedAttemptCount($this->user));
        $this->assertTrue($this->user->fresh()->mfa_enabled);

        // Wrong code with correct password counts as a failed attempt.
        $this->postJson('/api/v1/mfa/disable', [
            'current_password' => 'pass',
            'code' => '999999',
        ])->assertStatus(422);
        $this->assertSame(1, $this->mfaService->failedAttemptCount($this->user));

        // Correct password + valid code disables MFA.
        $code = $this->mfaService->generateCode($secretData['secret']);

        $this->postJson('/api/v1/mfa/disable', [
            'current_password' => 'pass',
            'code' => $code,
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertFalse($this->user->fresh()->mfa_enabled);
    }

    #[Test]
    public function recovery_codes_can_be_regenerated_with_password(): void
    {
        $secretData = $this->enableMfaForUser();

        $this->postJson('/api/v1/mfa/recovery-codes/regenerate', ['current_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $response = $this->postJson('/api/v1/mfa/recovery-codes/regenerate', [
            'current_password' => 'pass',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $codes = $response->json('data.recovery_codes');
        $this->assertCount(10, $codes);

        // Old codes are gone; a fresh one works.
        $this->assertFalse(
            $this->mfaService->verifyRecoveryCode($this->user, 'AAAA-BBBB'),
            'Stale hard-coded code should not match'
        );
        $this->assertTrue($this->mfaService->verifyRecoveryCode($this->user, $codes[0]));

        // Regeneration is refused while MFA is disabled.
        $this->postJson('/api/v1/mfa/disable', [
            'current_password' => 'pass',
            'code' => $this->mfaService->generateCode($secretData['secret']),
        ])->assertStatus(200);

        $this->postJson('/api/v1/mfa/recovery-codes/regenerate', ['current_password' => 'pass'])
            ->assertStatus(409);
    }

    /**
     * Enable MFA directly for the user (bypassing enroll) and return the
     * generated secret data.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    private function enableMfaForUser(): array
    {
        $secretData = $this->mfaService->generateSecret($this->user->email);
        $this->mfaService->storeSecret($this->user, $secretData['secret']);
        $this->mfaService->generateRecoveryCodes($this->user);
        $this->mfaService->enableMfa($this->user);

        return $secretData;
    }

    /**
     * A representative endpoint behind mfa.verified plus a payload that
     * passes validation once the middleware lets the request through.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function protectedEndpoint(): array
    {
        $branch = $this->branch;
        $currency = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $counter = Counter::factory()->create(['code' => 'MAIN'.substr(uniqid(), -4), 'branch_id' => $branch->id]);
        $customer = Customer::factory()->create();
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'branch_id' => $branch->id,
        ]);

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'currency_code' => $currency->code,
            'till_id' => $counter->code,
            'branch_id' => $branch->id,
        ]);

        $creationService = $this->mock(TransactionCreationServiceInterface::class);
        $creationService->shouldReceive('prepareAndCreate')
            ->andReturn($transaction);

        return ['/api/v1/transactions', [
            'customer_id' => $customer->id,
            'type' => 'Buy',
            'currency_code' => $currency->code,
            'amount_foreign' => $transaction->amount_foreign,
            'rate' => $transaction->rate,
            'purpose' => $transaction->purpose,
            'source_of_funds' => $transaction->source_of_funds,
            'till_id' => $counter->code,
        ]];
    }
}
