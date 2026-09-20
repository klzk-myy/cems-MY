<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add stock_transfers.source_branch_id / destination_branch_id FKs on an
 * existing (non-destructive) database and backfill them from the legacy
 * name/code snapshot columns. Rows whose names resolve to no branch are
 * reported and left null rather than guessed.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallStockTransferBranchFks extends Command
{
    protected $signature = 'stock-transfers:install-branch-fks';

    protected $description = 'Add and backfill real branch FKs on stock_transfers';

    public function handle(): int
    {
        if (! Schema::hasTable('stock_transfers')) {
            $this->warn('stock_transfers table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('stock_transfers', 'source_branch_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->unsignedBigInteger('source_branch_id')->nullable()->after('status');
                $table->unsignedBigInteger('destination_branch_id')->nullable()->after('source_branch_id');
                $table->index('source_branch_id', 'stock_transfers_source_branch_index');
                $table->index('destination_branch_id', 'stock_transfers_destination_branch_index');
                $table->foreign('source_branch_id')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('destination_branch_id')->references('id')->on('branches')->nullOnDelete();
            });
            $this->info('Added stock_transfers branch FK columns.');
        } else {
            $this->info('Branch FK columns already exist — skipping column add.');
        }

        // Backfill: resolve the stored name/code snapshots to branch ids.
        $resolved = 0;
        $unresolved = 0;

        DB::table('stock_transfers')
            ->whereNull('source_branch_id')
            ->whereNotNull('source_branch_name')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$resolved, &$unresolved) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach (['source' => 'source_branch_name', 'destination' => 'destination_branch_name'] as $side => $nameColumn) {
                        $identifier = $row->{$nameColumn};

                        if ($identifier === null || trim($identifier) === '') {
                            continue;
                        }

                        $branchId = Branch::query()
                            ->where('name', $identifier)
                            ->orWhere('code', $identifier)
                            ->value('id');

                        if ($branchId !== null) {
                            $updates["{$side}_branch_id"] = $branchId;
                        }
                    }

                    if ($updates === []) {
                        $unresolved++;
                        $this->warn("transfer {$row->id} ({$row->transfer_number}): no branch matched — left null");

                        continue;
                    }

                    DB::table('stock_transfers')->where('id', $row->id)->update($updates);
                    $resolved++;
                }
            }, 'id');

        $this->info("Backfill complete: {$resolved} transfers resolved, {$unresolved} left unresolved.");

        return self::SUCCESS;
    }
}
