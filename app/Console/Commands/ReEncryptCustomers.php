<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Domain\EncryptionConfigurationException;
use App\Models\Customer;
use App\Models\CustomerRelation;
use App\Services\System\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rotates customer PII encryption from previous credentials to the current ones.
 *
 * Usage: php artisan customers:re-encrypt {--old-salt=} {--old-key=} [--force]
 *
 * When run interactively, the previous credentials may be omitted from the
 * command line; they are then requested via hidden prompts so secrets never
 * appear in the process list or shell history.
 *
 * Decrypts id_number_encrypted (plus address / phone / employer_address when
 * present) with the previous APP_KEY + APP_ENCRYPTION_SALT, re-encrypts with
 * the current environment values, and recomputes the blind-index hash columns
 * so duplicate-identity checks and searches keep working after rotation.
 */
class ReEncryptCustomers extends Command
{
    protected $signature = 'customers:re-encrypt
                            {--old-salt= : Previous APP_ENCRYPTION_SALT value}
                            {--old-key= : Previous APP_KEY value}
                            {--chunk=500 : Number of records processed per transaction chunk}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Re-encrypt customer PII from previous APP_KEY/APP_ENCRYPTION_SALT to current credentials';

    public function handle(): int
    {
        $oldSalt = (string) $this->option('old-salt');
        $oldKey = (string) $this->option('old-key');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if (($oldSalt === '' || $oldKey === '') && $this->stdinIsInteractive()) {
            if ($oldSalt === '') {
                $oldSalt = (string) $this->secret('Previous APP_ENCRYPTION_SALT');
            }

            if ($oldKey === '') {
                $oldKey = (string) $this->secret('Previous APP_KEY');
            }
        }

        if ($oldSalt === '' || $oldKey === '') {
            $this->error('Both --old-key and --old-salt are required.');

            return self::FAILURE;
        }

        try {
            $oldService = new EncryptionService($oldKey, $oldSalt);
            $newService = new EncryptionService;
        } catch (EncryptionConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('This will re-encrypt customer PII columns in-place using the current APP_KEY / APP_ENCRYPTION_SALT.');
            $this->warn('Back up your database before running this command.');

            if (! $this->confirm('Continue?', false)) {
                $this->info('Operation cancelled.');

                return self::FAILURE;
            }
        }

        $customerCount = 0;
        $relationCount = 0;
        /** @var list<string> $failures */
        $failures = [];

        Customer::query()
            ->whereNotNull('id_number_encrypted')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($customers) use (&$customerCount, &$failures, $oldService, $newService): void {
                DB::transaction(function () use ($customers, &$customerCount, &$failures, $oldService, $newService): void {
                    foreach ($customers as $customer) {
                        if ($this->rotateCustomer($customer, $oldService, $newService, $failures)) {
                            $customerCount++;
                        }
                    }
                });
            });

        CustomerRelation::query()
            ->whereNotNull('id_number_encrypted')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($relations) use (&$relationCount, &$failures, $oldService, $newService): void {
                DB::transaction(function () use ($relations, &$relationCount, &$failures, $oldService, $newService): void {
                    foreach ($relations as $relation) {
                        if ($this->rotateRelation($relation, $oldService, $newService, $failures)) {
                            $relationCount++;
                        }
                    }
                });
            });

        $this->info('Re-encryption run complete.');
        $this->table(['Metric', 'Count'], [
            ['Customers re-encrypted', $customerCount],
            ['Customer relations re-encrypted', $relationCount],
            ['Failures', count($failures)],
        ]);

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            $this->warn('Failed rows were left untouched. Re-run the command after resolving the issues above.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Whether the command can safely prompt for missing credentials: only
     * when STDIN is a real TTY (secrets via hidden prompt). Piped/automated
     * runs keep failing fast instead of hanging on a prompt.
     */
    protected function stdinIsInteractive(): bool
    {
        return defined('STDIN') && is_resource(STDIN) && stream_isatty(STDIN);
    }

    /**
     * Rotate a single customer row in place. Returns true when updated.
     *
     * @param  list<string>  $failures
     */
    protected function rotateCustomer(Customer $customer, EncryptionService $oldService, EncryptionService $newService, array &$failures): bool
    {
        $label = "Customer #{$customer->id}";

        $plaintext = $oldService->decrypt((string) $customer->id_number_encrypted);
        if ($plaintext === null) {
            $failures[] = "{$label}: cannot decrypt id_number_encrypted with the provided old credentials";

            return false;
        }

        $reEncrypted = $newService->encrypt($plaintext);
        if ($newService->decrypt($reEncrypted) !== $plaintext) {
            $failures[] = "{$label}: roundtrip verification failed for id_number_encrypted";

            return false;
        }

        $customer->id_number_encrypted = $reEncrypted;
        $customer->id_number_hash = $newService->hash($plaintext);

        // Secondary encrypted columns - rotated best-effort; a failure here is
        // reported but does not roll back the ID number rotation above.
        foreach (['address', 'phone'] as $column) {
            $value = $customer->{$column};
            if (empty($value)) {
                continue;
            }

            $columnPlaintext = $oldService->decrypt((string) $value);
            if ($columnPlaintext === null) {
                $failures[] = "{$label}: cannot decrypt {$column} with the provided old credentials";

                continue;
            }

            $customer->{$column} = $newService->encrypt($columnPlaintext);

            if ($column === 'phone') {
                $customer->phone_hash = $newService->hash($columnPlaintext);
            }
        }

        $value = $customer->employer_address;
        if (! empty($value)) {
            $employerPlaintext = $oldService->decrypt((string) $value);
            if ($employerPlaintext === null) {
                $failures[] = "{$label}: cannot decrypt employer_address with the provided old credentials";
            } else {
                $customer->employer_address = $newService->encrypt($employerPlaintext);
            }
        }

        if ($customer->isDirty()) {
            $customer->save();

            return true;
        }

        return false;
    }

    /**
     * Rotate a single customer relation row in place. Returns true when updated.
     *
     * @param  list<string>  $failures
     */
    protected function rotateRelation(CustomerRelation $relation, EncryptionService $oldService, EncryptionService $newService, array &$failures): bool
    {
        $label = "Customer relation #{$relation->id}";

        $plaintext = $oldService->decrypt((string) $relation->id_number_encrypted);
        if ($plaintext === null) {
            $failures[] = "{$label}: cannot decrypt id_number_encrypted with the provided old credentials";

            return false;
        }

        $reEncrypted = $newService->encrypt($plaintext);
        if ($newService->decrypt($reEncrypted) !== $plaintext) {
            $failures[] = "{$label}: roundtrip verification failed for id_number_encrypted";

            return false;
        }

        $relation->id_number_encrypted = $reEncrypted;

        if ($relation->isDirty()) {
            $relation->save();

            return true;
        }

        return false;
    }
}
