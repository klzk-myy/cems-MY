<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes to transactions table for production performance.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Individual foreign key indexes (Laravel adds these automatically but be explicit)
            if (! $this->hasIndex('transactions', 'transactions_customer_id_index')) {
                $table->index('customer_id', 'transactions_customer_id_index');
            }
            if (! $this->hasIndex('transactions', 'transactions_user_id_index')) {
                $table->index('user_id', 'transactions_user_id_index');
            }
            if (! $this->hasIndex('transactions', 'transactions_branch_id_index')) {
                $table->index('branch_id', 'transactions_branch_id_index');
            }

            // Date filtering index (critical for reporting)
            if (! $this->hasIndex('transactions', 'transactions_created_at_index')) {
                $table->index('created_at', 'transactions_created_at_index');
            }

            // Composite indexes for common query patterns
            // For customer transaction history queries
            if (! $this->hasIndex('transactions', 'transactions_customer_created_idx')) {
                $table->index(['customer_id', 'created_at'], 'transactions_customer_created_idx');
            }

            // For status-based filtering with date range (dashboard queries)
            if (! $this->hasIndex('transactions', 'transactions_status_created_idx')) {
                $table->index(['status', 'created_at'], 'transactions_status_created_idx');
            }

            // For branch + date range queries (MSB reports)
            if (! $this->hasIndex('transactions', 'transactions_branch_created_idx')) {
                $table->index(['branch_id', 'created_at'], 'transactions_branch_created_idx');
            }

            // For currency + date analysis
            if (! $this->hasIndex('transactions', 'transactions_currency_created_idx')) {
                $table->index(['currency_code', 'created_at'], 'transactions_currency_created_idx');
            }

            // For user activity reports
            if (! $this->hasIndex('transactions', 'transactions_user_created_idx')) {
                $table->index(['user_id', 'created_at'], 'transactions_user_created_idx');
            }

            // For approved_by lookups (manager approval reports)
            if (! $this->hasIndex('transactions', 'transactions_approved_by_index')) {
                $table->index('approved_by', 'transactions_approved_by_index');
            }

            // For cancellation queries
            if (! $this->hasIndex('transactions', 'transactions_cancelled_at_index')) {
                $table->index('cancelled_at', 'transactions_cancelled_at_index');
            }
        });
    }

    public function down(): void
    {
        // Note: Some indexes cannot be dropped because they are required by foreign key constraints
        // Indexes that CAN be safely dropped (not used by FKs):
        // - transactions_created_at_index
        // - transactions_customer_created_idx
        // - transactions_status_created_idx
        // - transactions_branch_created_idx
        // - transactions_user_created_idx
        // - transactions_cancelled_at_index
        //
        // Indexes that CANNOT be dropped (required by FKs):
        // - transactions_customer_id_index (FK to customers)
        // - transactions_user_id_index (FK to users)
        // - transactions_branch_id_index (FK to branches)
        // - transactions_currency_created_idx (FK to currencies)
        // - transactions_approved_by_index (FK to users)
        // - transactions_cancelled_by_foreign uses approved_by

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_created_at_index');
            $table->dropIndex('transactions_customer_created_idx');
            $table->dropIndex('transactions_status_created_idx');
            $table->dropIndex('transactions_branch_created_idx');
            $table->dropIndex('transactions_user_created_idx');
            $table->dropIndex('transactions_cancelled_at_index');
        });
    }

    /**
     * Check if index exists (for idempotency)
     */
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
