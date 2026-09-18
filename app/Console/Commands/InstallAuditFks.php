<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Add the foreign keys the audit found missing on an existing
 * (non-destructive) database. SchemaSeeder only runs on fresh installs,
 * so this command is the supported upgrade path for live databases.
 * Idempotent — safe to re-run.
 *
 * Each FK is preceded by an orphan scan: nullable columns have stray
 * values SET NULL (logged), while NOT NULL columns abort with a report
 * so the data problem is never silently hidden. A supporting index is
 * ensured before every ADD CONSTRAINT.
 */
class InstallAuditFks extends Command
{
    protected $signature = 'db:install-audit-fks {--force : Run without interactive confirmation}';

    protected $description = 'Add missing foreign keys and supporting indexes on an existing database';

    /**
     * [ table, column, referencedTable, referencedColumn, onDelete,
     *   constraintName, supportingIndexName ]
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    private const FKS = [
        ['transactions', 'counter_id', 'counters', 'id', 'SET NULL', 'transactions_counter_id_foreign', 'transactions_counter_id_index'],
        ['transactions', 'compliance_cleared_by', 'users', 'id', 'SET NULL', 'transactions_compliance_cleared_by_foreign', 'transactions_compliance_cleared_by_index'],
        ['transactions', 'rate_override_approved_by', 'users', 'id', 'SET NULL', 'transactions_rate_override_approved_by_foreign', 'transactions_rate_override_approved_by_index'],
        ['stock_reservations', 'transaction_id', 'transactions', 'id', 'RESTRICT', 'stock_reservations_transaction_id_foreign', 'stock_reservations_transaction_id_index'],
        ['stock_reservations', 'created_by', 'users', 'id', 'RESTRICT', 'stock_reservations_created_by_foreign', 'stock_reservations_created_by_index'],
        ['stock_reservations', 'currency_code', 'currencies', 'code', 'RESTRICT', 'stock_reservations_currency_code_foreign', 'stock_reservations_currency_code_till_id_status_index'],
        ['pool_remittances', 'out_journal_entry_id', 'journal_entries', 'id', 'RESTRICT', 'pool_remittances_out_journal_entry_id_foreign', 'pool_remittances_out_journal_entry_id_index'],
        ['pool_remittances', 'ack_journal_entry_id', 'journal_entries', 'id', 'RESTRICT', 'pool_remittances_ack_journal_entry_id_foreign', 'pool_remittances_ack_journal_entry_id_index'],
        ['pool_remittances', 'currency_code', 'currencies', 'code', 'RESTRICT', 'pool_remittances_currency_code_foreign', 'pool_remittances_currency_code_index'],
        ['currency_positions', 'branch_id', 'branches', 'id', 'RESTRICT', 'currency_positions_branch_id_foreign', 'currency_positions_branch_id_index'],
        ['branch_pools', 'currency_code', 'currencies', 'code', 'RESTRICT', 'branch_pools_currency_code_foreign', 'branch_pools_currency_code_index'],
        ['teller_allocations', 'rejected_by', 'users', 'id', 'SET NULL', 'teller_allocations_rejected_by_foreign', 'teller_allocations_rejected_by_index'],
        ['teller_allocations', 'currency_code', 'currencies', 'code', 'RESTRICT', 'teller_allocations_currency_code_foreign', 'teller_allocations_currency_code_index'],
        ['stock_transfer_items', 'currency_code', 'currencies', 'code', 'RESTRICT', 'stock_transfer_items_currency_code_foreign', 'stock_transfer_items_currency_code_index'],
        ['aml_rules', 'created_by', 'users', 'id', 'SET NULL', 'aml_rules_created_by_foreign', 'aml_rules_created_by_index'],
        ['customer_risk_profiles', 'locked_by', 'users', 'id', 'SET NULL', 'customer_risk_profiles_locked_by_foreign', 'customer_risk_profiles_locked_by_index'],
        ['edd_document_requests', 'verified_by', 'users', 'id', 'SET NULL', 'edd_document_requests_verified_by_foreign', 'edd_document_requests_verified_by_index'],
        ['transaction_confirmations', 'user_id', 'users', 'id', 'RESTRICT', 'transaction_confirmations_user_id_foreign', 'transaction_confirmations_user_id_index'],
        ['transaction_confirmations', 'confirmed_by', 'users', 'id', 'SET NULL', 'transaction_confirmations_confirmed_by_foreign', 'transaction_confirmations_confirmed_by_index'],
        ['transaction_imports', 'imported_by', 'users', 'id', 'RESTRICT', 'transaction_imports_imported_by_foreign', 'transaction_imports_imported_by_index'],
        ['journal_entries', 'cost_center_id', 'cost_centers', 'id', 'SET NULL', 'journal_entries_cost_center_id_foreign', 'idx_journal_entries_cost_center'],
        ['journal_entries', 'department_id', 'departments', 'id', 'SET NULL', 'journal_entries_department_id_foreign', 'idx_journal_entries_department'],
        ['chart_of_accounts', 'cost_center_id', 'cost_centers', 'id', 'SET NULL', 'chart_of_accounts_cost_center_id_foreign', 'chart_of_accounts_cost_center_id_index'],
        ['chart_of_accounts', 'department_id', 'departments', 'id', 'SET NULL', 'chart_of_accounts_department_id_foreign', 'chart_of_accounts_department_id_index'],
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Add foreign keys on '.DB::getDatabaseName().'?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $this->ensureIndex('transactions', 'transactions_till_created_idx', 'ADD INDEX transactions_till_created_idx (till_id, created_at)');

        foreach (self::FKS as [$table, $column, $refTable, $refColumn, $onDelete, $fkName, $indexName]) {
            if ($this->fkExists($table, $column, $refTable)) {
                continue;
            }

            if (! $this->resolveOrphans($table, $column, $refTable, $refColumn, $onDelete)) {
                return self::FAILURE;
            }

            $this->ensureIndex($table, $indexName, "ADD INDEX {$indexName} ({$column})");
            $this->runDdl("ALTER TABLE {$table} ADD CONSTRAINT {$fkName} FOREIGN KEY ({$column}) REFERENCES {$refTable} ({$refColumn}) ON DELETE {$onDelete}");
            $this->line("{$table}.{$column} -> {$refTable}({$refColumn}) ON DELETE {$onDelete}");
        }

        $this->info('Audit FK installation complete.');

        return self::SUCCESS;
    }

    /**
     * SET NULL stray child values when the column allows it; abort on
     * NOT NULL columns — never hide an integrity problem.
     */
    private function resolveOrphans(string $table, string $column, string $refTable, string $refColumn, string $onDelete): bool
    {
        $orphans = DB::table($table)
            ->leftJoin($refTable, "{$table}.{$column}", '=', "{$refTable}.{$refColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("{$refTable}.{$refColumn}")
            ->count();

        if ($orphans === 0) {
            return true;
        }

        if ($onDelete === 'SET NULL') {
            $this->warn("{$table}.{$column}: {$orphans} orphan value(s) nulled before FK");
            DB::table($table)
                ->leftJoin($refTable, "{$table}.{$column}", '=', "{$refTable}.{$refColumn}")
                ->whereNotNull("{$table}.{$column}")
                ->whereNull("{$refTable}.{$refColumn}")
                ->update(["{$table}.{$column}" => null]);

            return true;
        }

        $sample = DB::table($table)
            ->leftJoin($refTable, "{$table}.{$column}", '=', "{$refTable}.{$refColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("{$refTable}.{$refColumn}")
            ->limit(10)
            ->pluck("{$table}.{$column}");

        $this->error("{$table}.{$column}: {$orphans} orphan value(s) cannot be nulled (NOT NULL column). Resolve manually. Sample: ".$sample->implode(', '));

        return false;
    }

    private function fkExists(string $table, string $column, string $refTable): bool
    {
        return DB::selectOne(
            'SELECT 1 AS x FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [DB::getDatabaseName(), $table, $column, $refTable]
        ) !== null;
    }

    private function ensureIndex(string $table, string $indexName, string $ddl): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS x FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [DB::getDatabaseName(), $table, $indexName]
        ) !== null;

        if ($exists) {
            return;
        }

        $this->runDdl("ALTER TABLE {$table} {$ddl}");
        $this->line("{$table}: added {$indexName}");
    }

    private function runDdl(string $sql): void
    {
        DB::statement($sql);
    }
}
