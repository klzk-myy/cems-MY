<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add str_reports.bnm_reference UNIQUE on an existing (non-destructive)
 * database. A BNM filing reference must never be recorded on two STRs —
 * the service-level re-check in StrReportService::submit() cannot see
 * another transaction's uncommitted write, so the index is the
 * authoritative guard.
 *
 * SchemaSeeder only runs on fresh installs, so this command is the
 * supported upgrade path for live databases. Idempotent — safe to re-run.
 * Refuses to run while duplicate non-null references exist; resolve them
 * manually and re-run.
 */
class InstallStrBnmReferenceUnique extends Command
{
    protected $signature = 'str:install-bnm-reference-unique';

    protected $description = 'Add UNIQUE index on str_reports.bnm_reference to prevent duplicate BNM references';

    public function handle(): int
    {
        if (! Schema::hasTable('str_reports')) {
            $this->warn('str_reports table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        // Index name must mirror database/seeders/SchemaSeeder.php exactly —
        // that file is the schema source of truth.
        if (Schema::hasIndex('str_reports', 'str_reports_bnm_reference_unique')) {
            $this->info('str_reports_bnm_reference_unique already exists — skipping.');

            return self::SUCCESS;
        }

        $duplicates = DB::table('str_reports')
            ->whereNotNull('bnm_reference')
            ->select('bnm_reference', DB::raw('COUNT(*) as occurrences'))
            ->groupBy('bnm_reference')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('occurrences', 'bnm_reference');

        if ($duplicates->isNotEmpty()) {
            $this->error('Cannot add the unique index — duplicate bnm_reference values exist:');

            foreach ($duplicates as $reference => $count) {
                $this->line("  {$reference} ({$count} rows)");
            }

            $this->warn('Resolve the duplicates, then re-run this command.');

            return self::FAILURE;
        }

        Schema::table('str_reports', function (Blueprint $table) {
            $table->unique('bnm_reference', 'str_reports_bnm_reference_unique');
        });

        $this->info('Added str_reports_bnm_reference_unique index.');

        return self::SUCCESS;
    }
}
