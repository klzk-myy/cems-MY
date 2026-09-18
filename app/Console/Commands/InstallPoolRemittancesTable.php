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
            $this->info('pool_remittances already exists — skipping.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        Schema::create('pool_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_number');
            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_branch_id');
            $table->string('currency_code');
            $table->decimal('amount', 20, 4);
            $table->enum('status', ['Pending', 'Acknowledged', 'Cancelled'])->default('Pending');
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('initiated_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('out_journal_entry_id')->nullable();
            $table->unsignedBigInteger('ack_journal_entry_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('remittance_number', 'pool_remittances_remittance_number_unique');
            $table->index(['status', 'to_branch_id'], 'pool_remittances_status_to_branch_index');
            $table->foreign('from_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        });

        $this->info('Created pool_remittances table.');

        return self::SUCCESS;
    }
}
