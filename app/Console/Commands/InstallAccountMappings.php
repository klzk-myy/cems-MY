<?php

namespace App\Console\Commands;

use Database\Seeders\AccountMappingsSeeder;
use Database\Seeders\EnhancedChartOfAccountsSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Install the account_mappings table on an existing (non-destructive)
 * database and seed it. SchemaSeeder only runs on fresh installs, so this
 * command is the supported upgrade path for live databases: it creates the
 * table when missing, upserts the enum-backed chart of accounts (which adds
 * 2300 Inter-Branch Clearing), and seeds default mapping rows without
 * touching admin edits (AccountMappingsSeeder uses firstOrCreate).
 * Idempotent — safe to re-run.
 */
class InstallAccountMappings extends Command
{
    protected $signature = 'accounting:install-mappings';

    protected $description = 'Create/seed the account_mappings table and the 2300 clearing account on an existing database';

    public function handle(): int
    {
        if (! Schema::hasTable('account_mappings')) {
            // DDL must mirror database/seeders/SchemaSeeder.php exactly —
            // that file is the schema source of truth.
            Schema::create('account_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('account_code');
                $table->string('description')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique('key', 'account_mappings_key_unique');
                $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });

            $this->info('Created account_mappings table.');
        } else {
            $this->info('account_mappings table already exists — skipping creation.');
        }

        // Upserts the full AccountCode enum (adds 2300 Inter-Branch Clearing).
        (new EnhancedChartOfAccountsSeeder)->run();
        $this->info('Chart of accounts synced.');

        (new AccountMappingsSeeder)->run();
        $this->info('Account mappings seeded (existing overrides preserved).');

        return self::SUCCESS;
    }
}
