<?php

namespace App\Console\Commands;

use App\Enums\TellerAllocationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the teller_allocations.status enum to include 'cancelled' on an
 * existing (non-destructive) database. SchemaSeeder only runs on fresh
 * installs, so databases seeded before the status was added cannot store
 * it — branch settlement cancels stale requests into this status, and any
 * such write fails with SQLSTATE 01000/1265 on a pre-widening database.
 * Idempotent — safe to re-run.
 */
class InstallAllocationCancelledStatus extends Command
{
    protected $signature = 'allocations:install-cancelled-status';

    protected $description = 'Widen the teller_allocations.status enum to include cancelled on an existing database';

    public function handle(): int
    {
        if (! Schema::hasTable('teller_allocations')) {
            $this->warn('teller_allocations table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->info('Non-MySQL driver — no native ENUM to widen.');

            return self::SUCCESS;
        }

        $values = array_column(TellerAllocationStatus::cases(), 'value');
        $quoted = implode(',', array_map(
            fn (string $v) => "'".str_replace("'", "''", $v)."'",
            $values
        ));

        $column = DB::selectOne("SHOW COLUMNS FROM teller_allocations WHERE Field = 'status'");

        if (($column->Type ?? '') === 'enum('.$quoted.')') {
            $this->info('teller_allocations.status enum already current.');

            return self::SUCCESS;
        }

        DB::statement("ALTER TABLE teller_allocations MODIFY status ENUM({$quoted}) NOT NULL DEFAULT 'pending'");

        $this->info('teller_allocations.status enum widened to '.count($values).' values.');

        return self::SUCCESS;
    }
}
