<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the kpi_targets table on an existing (non-destructive) database.
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 *
 * DDL must mirror database/seeders/SchemaSeeder.php exactly — that file is
 * the schema source of truth.
 */
class InstallKpiTargetsTable extends Command
{
    protected $signature = 'kpi:install-targets-table';

    protected $description = 'Create the kpi_targets table on an existing database';

    public function handle(): int
    {
        if (Schema::hasTable('kpi_targets')) {
            $this->info('kpi_targets already exists — skipping.');

            return self::SUCCESS;
        }

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_metric', 64);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('branch_key')->storedAs('IFNULL(branch_id, 0)');
            $table->string('period', 16)->default('day');
            $table->decimal('target_value', 20, 4)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['kpi_metric', 'branch_key', 'period'], 'kpi_targets_metric_branch_period_unique');
            $table->index('branch_id', 'kpi_targets_branch_id_index');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        $this->info('Created kpi_targets table.');

        return self::SUCCESS;
    }
}
