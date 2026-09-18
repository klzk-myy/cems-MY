<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Normalize mismatched column types on an existing (non-destructive)
 * database so foreign keys can be added later. SchemaSeeder only runs on
 * fresh installs, so this command is the supported upgrade path for live
 * databases. Idempotent — safe to re-run.
 *
 * Changes applied:
 *  - int(11) reference columns -> BIGINT UNSIGNED (matches users.id,
 *    transactions.id, counters.id, cost_centers.id, departments.id)
 *  - currency_positions.branch_id -> BIGINT UNSIGNED NULL where NULL is the
 *    company-wide position (legacy 'HQ' sentinel rows are converted first),
 *    plus a generated branch_key column so uniqueness still covers the
 *    company-wide row (unique keys ignore NULLs).
 */
class InstallAuditTypeFixes extends Command
{
    protected $signature = 'db:install-audit-type-fixes {--force : Run without interactive confirmation}';

    protected $description = 'Normalize int/varchar reference columns to BIGINT UNSIGNED on an existing database';

    /**
     * int(11) columns -> BIGINT UNSIGNED, keyed by table then column.
     * Value is whether the column is nullable.
     *
     * @var array<string, array<string, bool>>
     */
    private const INT_COLUMNS = [
        'chart_of_accounts' => ['cost_center_id' => true, 'department_id' => true],
        'transactions' => ['rate_override_approved_by' => true, 'counter_id' => true],
        'aml_rules' => ['created_by' => true],
        'teller_allocations' => ['rejected_by' => true],
        'customer_risk_profiles' => ['locked_by' => true],
        'edd_document_requests' => ['verified_by' => true],
        'stock_reservations' => ['transaction_id' => false, 'created_by' => false],
        'transaction_confirmations' => ['user_id' => false, 'confirmed_by' => true],
        'transaction_imports' => ['imported_by' => false],
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Alter column types on '.DB::getDatabaseName().'?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        if (! $this->convertCurrencyPositionKeys()) {
            return self::FAILURE;
        }

        foreach (self::INT_COLUMNS as $table => $columns) {
            foreach ($columns as $column => $nullable) {
                $this->widenToBigint($table, $column, $nullable);
            }
        }

        $this->ensureIndex('transactions', 'transactions_counter_id_index', 'ADD INDEX transactions_counter_id_index (counter_id)');
        $this->ensureIndex('currency_positions', 'currency_positions_branch_id_index', 'ADD INDEX currency_positions_branch_id_index (branch_id)');

        $this->info('Audit type fixes complete.');

        return self::SUCCESS;
    }

    /**
     * Convert currency_positions.branch_id from varchar ('HQ' sentinel or
     * numeric string) to BIGINT UNSIGNED NULL, and replace the
     * (currency_code, branch_id) unique key with one over a generated
     * branch_key column so the company-wide (NULL) row stays unique.
     */
    private function convertCurrencyPositionKeys(): bool
    {
        // Legacy 'HQ' sentinel meant the company-wide position -> NULL.
        DB::table('currency_positions')->where('branch_id', 'HQ')->update(['branch_id' => null]);

        $bad = DB::table('currency_positions')
            ->whereNotNull('branch_id')
            ->whereRaw("branch_id NOT REGEXP '^[0-9]+$'")
            ->pluck('branch_id');

        if ($bad->isNotEmpty()) {
            $this->error('currency_positions.branch_id has non-numeric values: '.$bad->implode(', '));

            return false;
        }

        if (! $this->isBigintUnsigned('currency_positions', 'branch_id')) {
            $this->runDdl('ALTER TABLE currency_positions MODIFY branch_id BIGINT UNSIGNED NULL');
            $this->line('currency_positions.branch_id -> BIGINT UNSIGNED NULL');
        }

        if (! $this->hasColumn('currency_positions', 'branch_key')) {
            $this->runDdl('ALTER TABLE currency_positions ADD branch_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(branch_id, 0)) STORED AFTER branch_id');
            $this->line('currency_positions.branch_key generated column added');
        }

        foreach (['currency_positions_currency_code_branch_id_unique', 'currency_positions_currency_branch_unique'] as $legacyUnique) {
            if ($this->indexExists('currency_positions', $legacyUnique)) {
                $this->runDdl("ALTER TABLE currency_positions DROP INDEX {$legacyUnique}");
                $this->line("dropped {$legacyUnique}");
            }
        }

        $this->ensureIndex(
            'currency_positions',
            'currency_positions_currency_code_branch_key_unique',
            'ADD UNIQUE INDEX currency_positions_currency_code_branch_key_unique (currency_code, branch_key)'
        );

        return true;
    }

    private function widenToBigint(string $table, string $column, bool $nullable): void
    {
        if ($this->isBigintUnsigned($table, $column)) {
            return;
        }

        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->runDdl("ALTER TABLE {$table} MODIFY {$column} BIGINT UNSIGNED {$null}");
        $this->line("{$table}.{$column} -> BIGINT UNSIGNED {$null}");
    }

    private function ensureIndex(string $table, string $indexName, string $ddl): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $this->runDdl("ALTER TABLE {$table} {$ddl}");
        $this->line("{$table}: added {$indexName}");
    }

    private function isBigintUnsigned(string $table, string $column): bool
    {
        $type = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        );

        return $type !== null && str_contains(strtolower($type->COLUMN_TYPE), 'bigint') && str_contains(strtolower($type->COLUMN_TYPE), 'unsigned');
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        ) !== null;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::selectOne(
            'SELECT 1 AS x FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [DB::getDatabaseName(), $table, $indexName]
        ) !== null;
    }

    private function runDdl(string $sql): void
    {
        DB::statement($sql);
    }
}
