<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign key specifications: table => [column => referenced_table]
     */
    private array $fks = [
        'account_ledger' => ['branch_id' => 'branches'],
        'accounting_periods' => ['fiscal_year_id' => 'fiscal_years'],
        'chart_of_accounts' => [
            'cost_center_id' => 'cost_centers',
            'department_id' => 'departments',
        ],
        'counters' => ['branch_id' => 'branches'],
        'counter_sessions' => ['teller_allocation_id' => 'teller_allocations'],
        'currency_positions' => ['branch_id' => 'branches'],
        'enhanced_diligence_records' => ['edd_template_id' => 'edd_questionnaire_templates'],
        'journal_entries' => [
            'cost_center_id' => 'cost_centers',
            'department_id' => 'departments',
        ],
        'journal_lines' => ['branch_id' => 'branches'],
        'stock_reservations' => ['transaction_id' => 'transactions'],
        'till_balances' => [
            'branch_id' => 'branches',
            'teller_allocation_id' => 'teller_allocations',
        ],
        'transaction_confirmations' => ['user_id' => 'users'],
        'transactions' => [
            'branch_id' => 'branches',
            'journal_entry_id' => 'journal_entries',
        ],
        'users' => ['branch_id' => 'branches'],
    ];

    /**
     * Check if column exists in table.
     */
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * Get column nullability from information_schema.
     * Returns true if nullable, false if not, null if unknown.
     */
    private function isColumnNullable(string $table, string $column): ?bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite: not enforced, treat as nullable for simplicity
            return true;
        }

        $database = DB::connection()->getDatabaseName();
        $result = DB::select(
            'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );
        if (empty($result)) {
            return null;
        }

        return $result[0]->IS_NULLABLE === 'YES';
    }

    /**
     * Check if a foreign key with the conventional name exists.
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();
        $fkName = $table.'_'.$column.'_foreign';

        if ($driver === 'sqlite') {
            // SQLite: we'll skip anyway
            return false;
        }

        $result = DB::select(
            'SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND CONSTRAINT_NAME = ?',
            [$database, $table, $column, $fkName]
        );

        return count($result) > 0;
    }

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'pgsql') {
            return;
        }

        foreach ($this->fks as $table => $columns) {
            foreach ($columns as $column => $referencedTable) {
                if (! $this->columnExists($table, $column)) {
                    continue;
                }

                if (! Schema::hasTable($referencedTable)) {
                    continue;
                }

                if ($this->foreignKeyExists($table, $column)) {
                    continue;
                }

                // For nullOnDelete, we need column to be nullable. If not, skip.
                $nullable = $this->isColumnNullable($table, $column);
                if ($nullable === false) {
                    // Column is NOT NULL; skip adding FK with nullOnDelete to avoid error.
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($column, $referencedTable) {
                    $t->foreign($column)
                        ->references('id')
                        ->on($referencedTable)
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'pgsql') {
            return;
        }

        foreach ($this->fks as $table => $columns) {
            foreach ($columns as $column => $referencedTable) {
                $fkName = $table.'_'.$column.'_foreign';
                // Only drop if exists
                if (! $this->foreignKeyExists($table, $column)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($fkName) {
                    $t->dropForeign($fkName);
                });
            }
        }
    }
};
