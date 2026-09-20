<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add stock_reservations.branch_id + the availability index on an existing
 * (non-destructive) database, and backfill it from the owning transaction's
 * branch. Reservations protect the branch-level currency position, so
 * availability checks sum pending reservations by branch_id — without the
 * column that sum is silently zero.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the supported
 * upgrade path for live databases. Idempotent — safe to re-run.
 */
class InstallStockReservationBranchId extends Command
{
    protected $signature = 'reservations:install-branch-id';

    protected $description = 'Add branch_id to stock_reservations, backfill from transactions, add the branch availability index';

    public function handle(): int
    {
        if (! Schema::hasTable('stock_reservations')) {
            $this->warn('stock_reservations table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (! Schema::hasColumn('stock_reservations', 'branch_id')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('till_id');
            });
            $this->info('Added stock_reservations.branch_id.');
        } else {
            $this->info('stock_reservations.branch_id already exists — skipping column add.');
        }

        // Backfill from the owning transaction's branch. Correlated subquery
        // is portable across MySQL and SQLite.
        $backfilled = DB::table('stock_reservations')
            ->whereNull('branch_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('transactions.id', 'stock_reservations.transaction_id');
            })
            ->update([
                'branch_id' => DB::raw('(select branch_id from transactions where transactions.id = stock_reservations.transaction_id)'),
            ]);

        if ($backfilled > 0) {
            $this->info("Backfilled branch_id on {$backfilled} stock_reservations row(s).");
        }

        if (! Schema::hasIndex('stock_reservations', 'stock_reservations_branch_availability_index')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->index(['branch_id', 'currency_code', 'status', 'expires_at'], 'stock_reservations_branch_availability_index');
            });
            $this->info('Added stock_reservations_branch_availability_index.');
        } else {
            $this->info('stock_reservations_branch_availability_index already exists — skipping.');
        }

        $this->info('stock_reservations branch_id install complete.');

        return self::SUCCESS;
    }
}
