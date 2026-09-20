<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add branch_closure_workflows.business_date / reopened_at /
 * active_workflow_key on an existing (non-destructive) database, backfill
 * business_date, and install the one-active-workflow unique index.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallBranchClosureBusinessDate extends Command
{
    protected $signature = 'closure:install-business-date';

    protected $description = 'Add business_date, reopened_at and the one-active-workflow unique key to branch_closure_workflows';

    public function handle(): int
    {
        if (! Schema::hasTable('branch_closure_workflows')) {
            $this->warn('branch_closure_workflows table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('branch_closure_workflows', 'business_date')) {
            Schema::table('branch_closure_workflows', function (Blueprint $table) {
                $table->date('business_date')->nullable()->after('status');
            });
            $this->info('Added branch_closure_workflows.business_date.');
        } else {
            $this->info('branch_closure_workflows.business_date already exists — skipping column add.');
        }

        // Backfill: the day a finalized workflow closed is its finalized
        // date; otherwise the initiation date. Correlated date() extraction
        // is portable across MySQL and SQLite.
        $backfilled = DB::table('branch_closure_workflows')
            ->whereNull('business_date')
            ->update([
                'business_date' => DB::raw('COALESCE(DATE(finalized_at), DATE(created_at))'),
            ]);

        if ($backfilled > 0) {
            $this->info("Backfilled business_date on {$backfilled} branch_closure_workflows row(s).");
        }

        if (! Schema::hasColumn('branch_closure_workflows', 'reopened_at')) {
            Schema::table('branch_closure_workflows', function (Blueprint $table) {
                $table->timestamp('reopened_at')->nullable()->after('finalized_at');
            });
            $this->info('Added branch_closure_workflows.reopened_at.');
        } else {
            $this->info('branch_closure_workflows.reopened_at already exists — skipping column add.');
        }

        if (! Schema::hasColumn('branch_closure_workflows', 'active_workflow_key')) {
            Schema::table('branch_closure_workflows', function (Blueprint $table) {
                $table->unsignedBigInteger('active_workflow_key')
                    ->storedAs("case when status in ('initiated','settled') then branch_id else null end")
                    ->nullable();
            });
            $this->info('Added branch_closure_workflows.active_workflow_key.');
        } else {
            $this->info('branch_closure_workflows.active_workflow_key already exists — skipping column add.');
        }

        if (! Schema::hasIndex('branch_closure_workflows', 'branch_closure_workflows_one_active_unique')) {
            Schema::table('branch_closure_workflows', function (Blueprint $table) {
                $table->unique('active_workflow_key', 'branch_closure_workflows_one_active_unique');
            });
            $this->info('Added branch_closure_workflows_one_active_unique index.');
        } else {
            $this->info('branch_closure_workflows_one_active_unique already exists — skipping.');
        }

        $this->info('branch_closure_workflows business_date install complete.');

        return self::SUCCESS;
    }
}
