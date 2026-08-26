<?php

namespace Tests\Feature;

use App\Console\Commands\ReEncryptCustomers;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Services\System\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ReEncryptCustomersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected EncryptionService $currentService;

    protected string $oldKey = 'base64:RkFLRV9PTERfS0VZX0ZPUl9ST1RBVElPTl9URVNUUw==';

    protected string $oldSalt = 'aaaa';

    protected string $currentSalt = 'bbbb';

    protected function setUp(): void
    {
        parent::setUp();

        // 64-character hex salts, mirroring production APP_ENCRYPTION_SALT format.
        $this->oldSalt = str_repeat('ab', 32);
        $this->currentSalt = str_repeat('cd', 32);

        // The "current" environment credentials the command rotates TO.
        config([
            'app.encryption_salt' => $this->currentSalt,
            'app.key' => 'base64:WlbBhnWV/8WIwLujIHnV4WqVBGA6jVh/6mAt4gt+NN8=',
        ]);

        $this->currentService = new EncryptionService;
    }

    /**
     * Persist a customer whose PII columns were encrypted under the OLD salt/key.
     */
    protected function createLegacyCustomer(): Customer
    {
        $oldService = new EncryptionService($this->oldKey, $this->oldSalt);

        /** @var Customer $customer */
        $customer = Customer::factory()->create();

        $customer->forceFill([
            'id_number_encrypted' => $oldService->encrypt('900101014222'),
            'id_number_hash' => 'legacy-id-hash',
            'address' => $oldService->encrypt('123 Jalan Legacy'),
            'phone' => $oldService->encrypt('+60123456789'),
            'phone_hash' => 'legacy-phone-hash',
            'employer_address' => $oldService->encrypt('456 Jalan Employer'),
        ])->save();

        return $customer;
    }

    #[Test]
    public function requires_old_key_and_old_salt_options(): void
    {
        $this->artisanCommand('customers:re-encrypt', ['--force' => true])
            ->expectsOutputToContain('--old-salt')
            ->assertFailed();

        $this->artisanCommand('customers:re-encrypt', [
            '--old-salt' => $this->oldSalt,
            '--force' => true,
        ])->assertFailed();
    }

    #[Test]
    public function rotates_customer_and_relation_pii_to_current_credentials(): void
    {
        $customer = $this->createLegacyCustomer();

        $oldService = new EncryptionService($this->oldKey, $this->oldSalt);

        /** @var CustomerRelation $relation */
        $relation = CustomerRelation::factory()->create([
            'customer_id' => $customer->id,
            'id_number_encrypted' => $oldService->encrypt('850505056633'),
        ]);

        $this->artisanCommand('customers:re-encrypt', [
            '--old-salt' => $this->oldSalt,
            '--old-key' => $this->oldKey,
            '--force' => true,
        ])->assertSuccessful();

        $customer->refresh();

        // All PII columns decrypt cleanly under CURRENT credentials.
        $this->assertSame('900101014222', $this->currentService->decrypt($customer->id_number_encrypted));
        $this->assertSame('123 Jalan Legacy', $this->currentService->decrypt($customer->address));
        $this->assertSame('+60123456789', $this->currentService->decrypt($customer->phone));
        $this->assertSame('456 Jalan Employer', $this->currentService->decrypt($customer->employer_address));

        // Blind indexes recomputed under the new derived key so lookups keep working.
        $this->assertSame(
            $this->currentService->hash('900101014222'),
            $customer->id_number_hash
        );
        $this->assertSame(
            $this->currentService->hash('+60123456789'),
            $customer->phone_hash
        );

        // Old credentials can no longer decrypt the rotated data.
        $this->assertNull($oldService->decrypt($customer->fresh()->id_number_encrypted));

        $relation->refresh();
        $this->assertSame('850505056633', $this->currentService->decrypt($relation->id_number_encrypted));
    }

    #[Test]
    public function reports_undecryptable_rows_as_failures_without_touching_them(): void
    {
        $good = $this->createLegacyCustomer();

        $corrupt = Customer::factory()->create();
        $corrupt->forceFill([
            'id_number_encrypted' => 'not-a-valid-ciphertext-value',
        ])->save();

        $originalCorruptValue = $corrupt->id_number_encrypted;

        $this->artisanCommand('customers:re-encrypt', [
            '--old-salt' => $this->oldSalt,
            '--old-key' => $this->oldKey,
            '--force' => true,
        ])->assertFailed();

        // The good row was rotated despite the bad one.
        $good->refresh();
        $this->assertSame('900101014222', $this->currentService->decrypt($good->id_number_encrypted));

        // The corrupt row was reported AND left untouched, not silently skipped.
        $this->assertSame($originalCorruptValue, $corrupt->fresh()->id_number_encrypted);
    }

    #[Test]
    public function aborts_without_confirmation_prompt_when_not_forced(): void
    {
        $this->createLegacyCustomer();

        $this->artisanCommand('customers:re-encrypt', [
            '--old-salt' => $this->oldSalt,
            '--old-key' => $this->oldKey,
        ])
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        // Nothing was rotated.
        $customer = Customer::first();
        $oldService = new EncryptionService($this->oldKey, $this->oldSalt);
        $this->assertSame('900101014222', $oldService->decrypt($customer->id_number_encrypted));
    }

    #[Test]
    public function prompts_for_hidden_credentials_when_stdin_is_interactive(): void
    {
        $customer = $this->createLegacyCustomer();

        // Interactive stdin cannot be simulated under the test runner; force
        // the interactive branch via a subclass override.
        $command = new class extends ReEncryptCustomers
        {
            protected function stdinIsInteractive(): bool
            {
                return true;
            }
        };
        $command->setLaravel($this->app);

        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            $this->fail('Failed to open in-memory stream for credentials');
        }

        fwrite($stream, $this->oldSalt."\n".$this->oldKey."\n");
        rewind($stream);

        $input = new ArrayInput(['--force' => true]);
        $input->bind($command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $exitCode = $command->run($input, new BufferedOutput);

        $this->assertSame(0, $exitCode);

        $customer->refresh();
        $this->assertSame('900101014222', $this->currentService->decrypt($customer->id_number_encrypted));
    }
}
