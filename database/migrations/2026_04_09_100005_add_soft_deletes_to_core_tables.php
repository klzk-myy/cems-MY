<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft deletes to core tables for data preservation.
     *
     * Soft deletes allow recovery of accidentally deleted records
     * and maintain referential integrity for related data.
     */
    public function up(): void
    {
        // Customers: Soft delete to preserve transaction history
        // Even if customer is "deleted", transactions must remain
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'customers_deleted_at_index');
            }
        });

        // Counters: Soft delete to preserve session history
        Schema::table('counters', function (Blueprint $table) {
            if (! Schema::hasColumn('counters', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'counters_deleted_at_index');
            }
        });

        // Users: Soft delete for audit trail
        // User records referenced by transactions should not be hard deleted
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'users_deleted_at_index');
            }
        });

        // Transactions: Already has cancellation, but soft delete for record removal
        // Note: Transactions have cancellation fields, soft delete is additional
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'transactions_deleted_at_index');
            }
        });

        // Branches: Soft delete for organizational history
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'branches_deleted_at_index');
            }
        });

        // Currencies: Soft delete (rarely used but for consistency)
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('currencies', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'currencies_deleted_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('counters', function (Blueprint $table) {
            $table->dropIndex('counters_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropIndex('currencies_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
