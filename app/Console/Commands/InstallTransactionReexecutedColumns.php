<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add transactions.reexecuted_by + reexecuted_at on an existing
 * (non-destructive) database. System re-execution via
 * ProcessTransactionRetry is not a human approval, so approved_by stays
 * null and the re-execution is recorded in these columns instead.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallTransactionReexecutedColumns extends Command
{
    protected $signature = 'transactions:install-reexecuted-columns';

    protected $description = 'Add reexecuted_by/reexecuted_at to transactions for system re-execution tracking';

    public function handle(): int
    {
        if (! Schema::hasTable('transactions')) {
            $this->warn('transactions table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('transactions', 'reexecuted_by')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('reexecuted_by')->nullable()->after('approved_at');
                $table->timestamp('reexecuted_at')->nullable()->after('reexecuted_by');
                $table->foreign('reexecuted_by')->references('id')->on('users')->nullOnDelete();
            });
            $this->info('Added transactions.reexecuted_by and reexecuted_at.');
        } else {
            $this->info('transactions.reexecuted_by already exists — skipping.');
        }

        $this->info('reexecuted columns install complete.');

        return self::SUCCESS;
    }
}
