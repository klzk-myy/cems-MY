<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add users.mfa_last_timestep on an existing (non-destructive) database.
 * The column records the last accepted TOTP window so a captured code
 * cannot be replayed inside its ~90-second validity.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallMfaLastTimestep extends Command
{
    protected $signature = 'mfa:install-last-timestep';

    protected $description = 'Add mfa_last_timestep to users for TOTP replay prevention';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->warn('users table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('users', 'mfa_last_timestep')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('mfa_last_timestep')->nullable()->after('mfa_secret');
            });
            $this->info('Added users.mfa_last_timestep.');
        } else {
            $this->info('users.mfa_last_timestep already exists — skipping column add.');
        }

        $this->info('mfa_last_timestep install complete.');

        return self::SUCCESS;
    }
}
