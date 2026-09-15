<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Rules\PasswordComplexityRule;
use App\Services\Customer\UserService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
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

    /**
     * Issue a reset token for a user and return it.
     */
    protected function issueResetToken(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $user->email]);

        $rawToken = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$rawToken): bool {
            $rawToken = $notification->token;

            return true;
        });

        return (string) $rawToken;
    }

    #[Test]
    public function reset_flow_rejects_password_under_minimum_length(): void
    {
        $user = User::factory()->create([
            'email' => 'policy-short@example.com',
            'password' => 'CurrentPass@123',
        ]);

        $originalHash = $user->password_hash;
        $token = $this->issueResetToken($user);

        // 7 characters — otherwise satisfies every complexity requirement.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Short7!',
            'password_confirmation' => 'Short7!',
        ])->assertInvalid(['password']);

        $user->refresh();

        $this->assertSame($originalHash, $user->password_hash);
    }

    #[Test]
    public function reset_flow_accepts_complex_password(): void
    {
        $user = User::factory()->create([
            'email' => 'policy-strong@example.com',
            'password' => 'CurrentPass@123',
        ]);

        $token = $this->issueResetToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPass@2026',
            'password_confirmation' => 'BrandNewPass@2026',
        ])->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(Hash::check('BrandNewPass@2026', $user->password_hash));
    }

    #[Test]
    public function reuse_prevention_blocks_recently_used_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reuse-blocked@example.com',
            'password' => 'SecondPass@123',
        ]);

        // The user previously used FirstPass@123 before switching to the
        // current one; archive it the way every password set point does.
        PasswordHistory::record($user->id, Hash::make('FirstPass@123'));

        $currentHash = $user->password_hash;
        $token = $this->issueResetToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'FirstPass@123',
            'password_confirmation' => 'FirstPass@123',
        ]);

        $response->assertInvalid(['password']);
        $this->assertStringContainsString(
            'recently used',
            (string) session('errors')->getBag('default')->first()
        );

        $user->refresh();

        $this->assertSame($currentHash, $user->password_hash);
    }

    #[Test]
    public function reuse_prevention_allows_password_outside_recent_window(): void
    {
        $user = User::factory()->create([
            'email' => 'reuse-allowed@example.com',
            'password' => 'CurrentPass@123',
        ]);

        $oldestHash = Hash::make('OldestPass@1!');

        // Fill the recent window (depth 5) with other passwords so the
        // oldest recorded hash falls outside the checked range.
        PasswordHistory::record($user->id, $oldestHash);

        for ($i = 1; $i <= 5; $i++) {
            PasswordHistory::record($user->id, Hash::make("FillerPass{$i}@abc"));
        }

        $token = $this->issueResetToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'OldestPass@1!',
            'password_confirmation' => 'OldestPass@1!',
        ])->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(Hash::check('OldestPass@1!', $user->password_hash));
    }

    #[Test]
    public function setup_request_rejects_weak_admin_password(): void
    {
        $this->post('/setup/step/2', [
            'admin_name' => 'Setup Admin',
            'admin_email' => 'setup-admin@example.com',
            'admin_password' => 'weakpass123',
            'admin_password_confirmation' => 'weakpass123',
        ])->assertInvalid(['admin_password']);
    }

    #[Test]
    public function setup_request_accepts_strong_admin_password(): void
    {
        $this->from(route('setup.wizard', ['step' => 2]))
            ->post('/setup/step/2', [
                'admin_name' => 'Setup Admin',
                'admin_email' => 'setup-admin-strong@example.com',
                'admin_password' => 'StrongSetup@2026',
                'admin_password_confirmation' => 'StrongSetup@2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('setup.wizard', ['step' => 3]));

        $this->assertSame('StrongSetup@2026', session('setup.admin.admin_password'));
    }

    #[Test]
    public function reset_flow_rejects_password_beyond_bcrypt_byte_limit(): void
    {
        $user = User::factory()->create([
            'email' => 'policy-long@example.com',
            'password' => 'CurrentPass@123',
        ]);

        $token = $this->issueResetToken($user);

        // 73 bytes that otherwise satisfy every complexity requirement.
        $long = str_repeat('Aa1!', 18).'A';

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $long,
            'password_confirmation' => $long,
        ])->assertInvalid(['password']);
    }

    #[Test]
    public function minimum_length_counts_characters_not_bytes(): void
    {
        // 7 characters but 10 bytes — ä, ß and ö are multibyte. A byte
        // count would have let this through the 8-character floor.
        $validator = Validator::make(
            ['password' => 'Päßwö1!'],
            ['password' => [new PasswordComplexityRule]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'at least 8 characters',
            $validator->errors()->first('password')
        );
    }

    #[Test]
    public function user_service_rejects_weak_password_without_http_layer(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'AdminPass@123',
        ]);

        $this->expectException(ValidationException::class);

        app(UserService::class)->createUser([
            'username' => 'weak-user',
            'email' => 'weak-user@example.com',
            'password' => 'weak',
            'role' => 'teller',
        ], $admin->id);
    }

    #[Test]
    public function changing_password_archives_previous_hash_in_history(): void
    {
        $user = User::factory()->create([
            'username' => 'history-user',
            'password' => 'BeforePass@123',
        ]);

        $previousHash = $user->password_hash;

        $this->actingAs($user)->post('/password/change', [
            'current_password' => 'BeforePass@123',
            'password' => 'AfterPass@456!',
            'password_confirmation' => 'AfterPass@456!',
        ])->assertRedirect('/dashboard');

        $user->refresh();

        $this->assertDatabaseHas('password_histories', [
            'user_id' => $user->id,
            'password' => $previousHash,
        ]);
        $this->assertTrue(Hash::check('AfterPass@456!', $user->password_hash));
    }
}
