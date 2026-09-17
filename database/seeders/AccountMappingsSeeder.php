<?php

namespace Database\Seeders;

use App\Enums\AccountMappingKey;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * Seeds the account_mappings table from the AccountMappingKey enum.
 *
 * firstOrCreate is deliberate: the table — not the enum — is the source of
 * truth once seeded, so rerunning this seeder must never clobber edits an
 * admin made through the account-mappings page. Where a legacy
 * config/accounting.php env var exists it wins over the enum default, so an
 * upgraded install keeps its configured accounts.
 */
class AccountMappingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AccountMappingKey::cases() as $key) {
            $legacy = $key->legacyConfigKey();
            $accountCode = $legacy !== null
                ? (Config::get($legacy) ?: $key->defaultAccount()->value)
                : $key->defaultAccount()->value;

            AccountMapping::firstOrCreate(
                ['key' => $key->value],
                [
                    'account_code' => $accountCode,
                    'description' => $key->usedBy(),
                ]
            );
        }
    }
}
