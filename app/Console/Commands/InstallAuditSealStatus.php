<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add system_logs.seal_status + seal_attempts on an existing
 * (non-destructive) database. seal_status='quarantined' marks a
 * permanently unsealable row so the hash chain can resume past it via
 * a GAP:<id> previous_hash marker instead of stalling forever.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallAuditSealStatus extends Command
{
    protected $signature = 'audit:install-seal-status';

    protected $description = 'Add seal_status and seal_attempts to system_logs for audit-chain quarantine';

    public function handle(): int
    {
        if (! Schema::hasTable('system_logs')) {
            $this->warn('system_logs table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('system_logs', 'seal_status')) {
            Schema::table('system_logs', function (Blueprint $table) {
                $table->string('seal_status', 16)->nullable()->after('entry_hash');
            });
            $this->info('Added system_logs.seal_status.');
        } else {
            $this->info('system_logs.seal_status already exists — skipping.');
        }

        if (! Schema::hasColumn('system_logs', 'seal_attempts')) {
            Schema::table('system_logs', function (Blueprint $table) {
                $table->unsignedSmallInteger('seal_attempts')->default(0)->after('seal_status');
            });
            $this->info('Added system_logs.seal_attempts.');
        } else {
            $this->info('system_logs.seal_attempts already exists — skipping.');
        }

        $this->info('audit seal-status install complete.');

        return self::SUCCESS;
    }
}
