<?php

namespace App\Console\Commands;

use App\Enums\RiskRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair a corrupted customers.risk_rating default on an existing
 * (non-destructive) database. Some pre-SchemaSeeder databases carry the
 * literal default 'Low' — quote characters included — so every newly
 * created customer stores the seven-character string 'Low' instead of
 * Low, and the RiskRating enum cast throws ValueError on read (customer
 * creation dies at risk scoring with "Failed to create customer").
 * Also sanitizes any rows already stored with the quoted value.
 * Idempotent — safe to re-run.
 */
class InstallCustomerRiskRatingDefault extends Command
{
    protected $signature = 'customers:fix-risk-rating-default';

    protected $description = 'Fix a quote-corrupted customers.risk_rating column default and stored values on an existing database';

    public function handle(): int
    {
        if (! Schema::hasTable('customers')) {
            $this->warn('customers table does not exist — nothing to do.');

            return self::SUCCESS;
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->info('Non-MySQL driver — column defaults do not carry this corruption.');

            return self::SUCCESS;
        }

        // Strip stray quote characters from stored values that otherwise
        // fail the RiskRating enum cast on read.
        $fixed = 0;
        foreach (RiskRating::cases() as $rating) {
            $fixed += DB::table('customers')
                ->where('risk_rating', "'{$rating->value}'")
                ->update(['risk_rating' => $rating->value]);
        }

        if ($fixed > 0) {
            $this->info("Normalized {$fixed} quoted risk_rating value(s).");
        }

        $column = DB::selectOne("SHOW COLUMNS FROM customers WHERE Field = 'risk_rating'");
        $default = $column->Default ?? null;

        if ($default === RiskRating::Low->value) {
            $this->info('customers.risk_rating default already correct.');

            return self::SUCCESS;
        }

        $escaped = str_replace("'", "''", RiskRating::Low->value);
        DB::statement("ALTER TABLE customers ALTER COLUMN risk_rating SET DEFAULT '{$escaped}'");

        $this->info('customers.risk_rating default repaired (was: '.var_export($default, true).').');

        return self::SUCCESS;
    }
}
