<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes to audit and system log tables.
     */
    public function up(): void
    {
        // System logs indexes (audit trail)
        Schema::table('system_logs', function (Blueprint $table) {
            // User lookups with date (already has composite, add individual)
            if (! $this->hasIndex('system_logs', 'system_logs_user_id_index')) {
                $table->index('user_id', 'system_logs_user_id_index');
            }

            // Entity type queries
            if (! $this->hasIndex('system_logs', 'system_logs_entity_type_index')) {
                $table->index('entity_type', 'system_logs_entity_type_index');
            }

            // IP address queries (security analysis)
            if (! $this->hasIndex('system_logs', 'system_logs_ip_address_index')) {
                $table->index('ip_address', 'system_logs_ip_address_index');
            }

            // Created at for date range queries
            if (! $this->hasIndex('system_logs', 'system_logs_created_at_index')) {
                $table->index('created_at', 'system_logs_created_at_index');
            }
        });

        // Till balances indexes
        Schema::table('till_balances', function (Blueprint $table) {
            if (! $this->hasIndex('till_balances', 'till_balances_date_index')) {
                $table->index('date', 'till_balances_date_index');
            }
            if (! $this->hasIndex('till_balances', 'till_balances_currency_date_idx')) {
                $table->index(['currency_code', 'date'], 'till_balances_currency_date_idx');
            }
            if (! $this->hasIndex('till_balances', 'till_balances_closed_at_index')) {
                $table->index('closed_at', 'till_balances_closed_at_index');
            }
        });

        // Currency positions indexes
        Schema::table('currency_positions', function (Blueprint $table) {
            if (! $this->hasIndex('currency_positions', 'currency_positions_currency_idx')) {
                $table->index('currency_code', 'currency_positions_currency_idx');
            }
            if (! $this->hasIndex('currency_positions', 'currency_positions_till_idx')) {
                $table->index('till_id', 'currency_positions_till_idx');
            }
        });

        // Flagged transactions indexes
        Schema::table('flagged_transactions', function (Blueprint $table) {
            if (! $this->hasIndex('flagged_transactions', 'flagged_trans_status_idx')) {
                $table->index('status', 'flagged_trans_status_idx');
            }
            if (! $this->hasIndex('flagged_transactions', 'flagged_trans_created_idx')) {
                $table->index('created_at', 'flagged_trans_created_idx');
            }
            if (! $this->hasIndex('flagged_transactions', 'flagged_trans_flag_type_idx')) {
                $table->index('flag_type', 'flagged_trans_flag_type_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex('system_logs_user_id_index');
            $table->dropIndex('system_logs_entity_type_index');
            $table->dropIndex('system_logs_ip_address_index');
            $table->dropIndex('system_logs_created_at_index');
        });

        Schema::table('till_balances', function (Blueprint $table) {
            $table->dropIndex('till_balances_date_index');
            $table->dropIndex('till_balances_currency_date_idx');
            $table->dropIndex('till_balances_closed_at_index');
        });

        Schema::table('currency_positions', function (Blueprint $table) {
            $table->dropIndex('currency_positions_currency_idx');
            $table->dropIndex('currency_positions_till_idx');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->dropIndex('flagged_trans_status_idx');
            $table->dropIndex('flagged_trans_created_idx');
            $table->dropIndex('flagged_trans_flag_type_idx');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $idx) {
            if ($idx['name'] === $index) {
                return true;
            }
        }

        return false;
    }
};
