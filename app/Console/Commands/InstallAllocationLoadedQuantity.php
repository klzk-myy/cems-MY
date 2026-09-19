<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add teller_allocations.loaded_quantity on an existing (non-destructive)
 * database. SchemaSeeder only runs on fresh installs, so this command is
 * the supported upgrade path for live databases. The column tracks the
 * portion of an allocation's custody currently parked in an open till —
 * custody that must release back to the pool when the session closes.
 * Idempotent — safe to re-run.
 */
class InstallAllocationLoadedQuantity extends Command
{
    protected $signature = 'allocations:install-loaded-quantity';

    protected $description = 'Add the loaded_quantity column to teller_allocations on an existing database';

    public function handle(): int
    {
        if (Schema::hasColumn('teller_allocations', 'loaded_quantity')) {
            $this->info('teller_allocations.loaded_quantity already exists — skipping.');

            return self::SUCCESS;
        }

        // DDL must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        Schema::table('teller_allocations', function (Blueprint $table) {
            $table->decimal('loaded_quantity', 20, 4)->default(0)->after('current_quantity');
        });

        $this->info('Added teller_allocations.loaded_quantity.');

        return self::SUCCESS;
    }
}
