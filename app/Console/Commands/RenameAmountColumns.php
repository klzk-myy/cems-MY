<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apply the canonical amount/quantity lexicon (Phase 4) to an existing
 * (non-destructive) database. SchemaSeeder already declares the new names
 * for fresh installs; this command is the supported upgrade path for live
 * databases. Idempotent — safe to re-run.
 *
 * Vocabulary: foreign-currency units use the `quantity` family, MYR
 * amounts use the `*_myr` suffix.
 */
class RenameAmountColumns extends Command
{
    protected $signature = 'db:rename-amount-columns';

    protected $description = 'Rename amount/quantity columns to the canonical lexicon on an existing database';

    /**
     * @var array<string, array<string, string>>
     */
    private const RENAMES = [
        'transactions' => [
            'amount_foreign' => 'quantity',
            'amount_local' => 'amount_myr',
        ],
        'stock_reservations' => [
            'amount_foreign' => 'quantity',
        ],
        'teller_allocations' => [
            'requested_amount' => 'requested_quantity',
            'allocated_amount' => 'allocated_quantity',
            'current_balance' => 'current_quantity',
            'loaded_balance' => 'loaded_quantity',
        ],
        'till_balances' => [
            'transaction_total' => 'transaction_total_myr',
            'foreign_total' => 'total_quantity',
            'buy_total_foreign' => 'buy_quantity',
            'sell_total_foreign' => 'sell_quantity',
        ],
        'revaluation_entries' => [
            'position_amount' => 'position_quantity',
            'gain_loss_amount' => 'gain_loss_myr',
        ],
        'pool_remittances' => [
            'amount' => 'amount_myr',
        ],
        'expenses' => [
            'amount' => 'amount_myr',
        ],
        'budgets' => [
            'budget_amount' => 'budget_myr',
            'actual_amount' => 'actual_myr',
        ],
        'str_reports' => [
            'trigger_amount' => 'trigger_amount_myr',
        ],
        'sanctions_analyses' => [
            'total_amount' => 'total_amount_myr',
        ],
        'branches' => [
            'petty_cash_float' => 'petty_cash_myr',
        ],
        'customers' => [
            'annual_volume_estimate' => 'annual_volume_myr',
        ],
    ];

    /**
     * Index names that embed a renamed column. Column renames keep the
     * index working; the name itself is updated for consistency.
     *
     * @var array<string, array<string, string>>
     */
    private const INDEX_RENAMES = [
        'transactions' => [
            'transactions_amount_local_index' => 'transactions_amount_myr_index',
        ],
    ];

    public function handle(): int
    {
        foreach (self::RENAMES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $this->warn("{$table} does not exist — skipping.");

                continue;
            }

            foreach ($columns as $from => $to) {
                $this->renameColumn($table, $from, $to);
            }
        }

        foreach (self::INDEX_RENAMES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $from => $to) {
                $this->renameIndex($table, $from, $to);
            }
        }

        return self::SUCCESS;
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        if (! Schema::hasColumn($table, $from)) {
            if (Schema::hasColumn($table, $to)) {
                $this->info("{$table}.{$to} already exists — skipping.");
            } else {
                $this->warn("{$table} has neither {$from} nor {$to} — skipping.");
            }

            return;
        }

        if (Schema::hasColumn($table, $to)) {
            $this->warn("{$table} has both {$from} and {$to} — manual reconciliation required, skipping.");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to) {
            $blueprint->renameColumn($from, $to);
        });

        $this->info("Renamed {$table}.{$from} -> {$to}.");
    }

    private function renameIndex(string $table, string $from, string $to): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $from)
            ->exists();

        if (! $exists) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
            str_replace('`', '``', $table),
            str_replace('`', '``', $from),
            str_replace('`', '``', $to),
        ));

        $this->info("Renamed index {$table}.{$from} -> {$to}.");
    }
}
