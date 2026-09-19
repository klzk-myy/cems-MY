<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the pool_remittances table on an existing (non-destructive)
 * database. SchemaSeeder only runs on fresh installs, so this command is
 * the supported upgrade path for live databases. The table tracks
 * two-step cash remittances between a trading branch and head office —
 * the value sits in the inter-branch clearing account (2300) until the
 * receiving side acknowledges. Idempotent — safe to re-run.
 */
class InstallPoolRemittancesTable extends Command
{
    protected $signature = 'pools:install-remittances-table';

    protected $description = 'Create the pool_remittances table on an existing database';

    public function handle(): int
    {
        if (Schema::hasTable('pool_remittances')) {
            // Upgrade path for tables created before later columns existed —
            // keeps live databases converged on the SchemaSeeder shape.
            $this->upgradeExistingTable();

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        Schema::create('pool_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_number');
            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_branch_id');
            $table->string('currency_code', 8);
            $table->decimal('amount_myr', 20, 4);
            $table->enum('status', ['pending', 'acknowledged', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('initiated_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('out_journal_entry_id')->nullable();
            $table->unsignedBigInteger('ack_journal_entry_id')->nullable();
            $table->unsignedBigInteger('cancel_journal_entry_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('remittance_number', 'pool_remittances_remittance_number_unique');
            $table->index(['status', 'to_branch_id'], 'pool_remittances_status_to_branch_index');
            $table->index('out_journal_entry_id', 'pool_remittances_out_journal_entry_id_index');
            $table->index('ack_journal_entry_id', 'pool_remittances_ack_journal_entry_id_index');
            $table->index('cancel_journal_entry_id', 'pool_remittances_cancel_journal_entry_id_index');
            $table->index('currency_code', 'pool_remittances_currency_code_index');
            $table->foreign('from_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('out_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('ack_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('cancel_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        $this->info('Created pool_remittances table.');

        return self::SUCCESS;
    }

    /**
     * Bring a table created by an earlier revision of this installer up to
     * the SchemaSeeder shape. Column-level drift (e.g. currency_code
     * sizing, missing FK constraints) is handled by the db:install-audit-*
     * commands — this only adds columns introduced after first deploy.
     */
    private function upgradeExistingTable(): void
    {
        if (! Schema::hasColumn('pool_remittances', 'cancel_journal_entry_id')) {
            Schema::table('pool_remittances', function (Blueprint $table) {
                $table->unsignedBigInteger('cancel_journal_entry_id')->nullable()->after('ack_journal_entry_id');
                $table->index('cancel_journal_entry_id', 'pool_remittances_cancel_journal_entry_id_index');
                $table->foreign('cancel_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            });
            $this->info('Added cancel_journal_entry_id to pool_remittances.');

            return;
        }

        $this->info('pool_remittances already exists — skipping.');
    }
}
