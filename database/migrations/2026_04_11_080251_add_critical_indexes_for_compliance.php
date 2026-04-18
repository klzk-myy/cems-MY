<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add critical composite indexes for performance and compliance reporting.
     */
    public function up(): void
    {
        // Helper to check if index exists
        $indexExists = function (string $table, string $index): bool {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $rows = DB::select("PRAGMA index_list('{$table}')");
                foreach ($rows as $row) {
                    if (($row->name ?? null) === $index) {
                        return true;
                    }
                }

                return false;
            }

            // MySQL / MariaDB
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

            return count($indexes) > 0;
        };

        // 1. transactions table - composite indexes for reporting
        if (! $indexExists('transactions', 'transactions_currency_created_idx')) {
            DB::statement('CREATE INDEX transactions_currency_created_idx ON transactions (currency_code, created_at)');
        }
        if (! $indexExists('transactions', 'transactions_status_created_idx')) {
            DB::statement('CREATE INDEX transactions_status_created_idx ON transactions (status, created_at)');
        }

        // 2. currency_positions table - unique composite index
        if (! $indexExists('currency_positions', 'currency_positions_currency_till_unique')) {
            DB::statement('CREATE UNIQUE INDEX currency_positions_currency_till_unique ON currency_positions (currency_code, till_id)');
        }

        // 3. flagged_transactions table - composite indexes for review queue
        if (! $indexExists('flagged_transactions', 'flagged_transactions_flag_type_created_idx')) {
            DB::statement('CREATE INDEX flagged_transactions_flag_type_created_idx ON flagged_transactions (flag_type, created_at)');
        }
        if (! $indexExists('flagged_transactions', 'flagged_transactions_status_created_idx')) {
            DB::statement('CREATE INDEX flagged_transactions_status_created_idx ON flagged_transactions (status, created_at)');
        }

        // 4. system_logs table - composite index for user activity queries
        if (! $indexExists('system_logs', 'system_logs_user_action_created_idx')) {
            DB::statement('CREATE INDEX system_logs_user_action_created_idx ON system_logs (user_id, action, created_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS transactions_currency_created_idx');
            DB::statement('DROP INDEX IF EXISTS transactions_status_created_idx');
            DB::statement('DROP INDEX IF EXISTS currency_positions_currency_till_unique');
            DB::statement('DROP INDEX IF EXISTS flagged_transactions_flag_type_created_idx');
            DB::statement('DROP INDEX IF EXISTS flagged_transactions_status_created_idx');
            DB::statement('DROP INDEX IF EXISTS system_logs_user_action_created_idx');

            return;
        }

        DB::statement('DROP INDEX transactions_currency_created_idx ON transactions');
        DB::statement('DROP INDEX transactions_status_created_idx ON transactions');
        DB::statement('DROP INDEX currency_positions_currency_till_unique ON currency_positions');
        DB::statement('DROP INDEX flagged_transactions_flag_type_created_idx ON flagged_transactions');
        DB::statement('DROP INDEX flagged_transactions_status_created_idx ON flagged_transactions');
        DB::statement('DROP INDEX system_logs_user_action_created_idx ON system_logs');
    }
};
