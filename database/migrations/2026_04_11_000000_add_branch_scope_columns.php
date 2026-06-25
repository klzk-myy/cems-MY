<?php

/**
 * Migration: Add Branch Scope Columns
 *
 * Adds branch_id foreign key (nullable, cascade delete) + index to:
 * - counters
 * - transactions
 * - journal_lines
 * - currency_positions
 * - till_balances
 *
 * Also adds parent_id FK to branches table for hierarchical structure.
 *
 * This migration is safe to run multiple times (idempotent).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add parent_id to branches table if not exists (for hierarchical structure)
        if (! Schema::hasColumn('branches', 'parent_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
                $table->index('parent_id');
            });
        }

        // Add branch_id to counters table after 'status' column
        if (! Schema::hasColumn('counters', 'branch_id')) {
            Schema::table('counters', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('status')->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }

        // Add branch_id to transactions table after 'user_id' column
        if (! Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('branches')->nullOnDelete();
                $table->index('branch_id', 'transactions_branch_id_fk_index');
            });
        }

        // Add branch_id to journal_lines table after 'journal_entry_id' column
        if (! Schema::hasColumn('journal_lines', 'branch_id')) {
            Schema::table('journal_lines', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('journal_entry_id')->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }

        // Add branch_id to currency_positions table after 'currency_code' column
        // Note: currency_positions currently has branch_id as string - we replace with FK
        if (! Schema::hasColumn('currency_positions', 'branch_id')) {
            Schema::table('currency_positions', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('currency_code')->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }

        // Add branch_id to till_balances table after 'counter_id' column
        if (! Schema::hasColumn('till_balances', 'branch_id')) {
            Schema::table('till_balances', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('currency_code')->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }
    }

    public function down(): void
    {
        // Drop from till_balances
        if (Schema::hasColumn('till_balances', 'branch_id')) {
            Schema::table('till_balances', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // Drop from currency_positions
        if (Schema::hasColumn('currency_positions', 'branch_id')) {
            Schema::table('currency_positions', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // Drop from journal_lines
        if (Schema::hasColumn('journal_lines', 'branch_id')) {
            Schema::table('journal_lines', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // Drop from transactions
        if (Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // Drop from counters
        if (Schema::hasColumn('counters', 'branch_id')) {
            Schema::table('counters', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // Drop parent_id from branches
        if (Schema::hasColumn('branches', 'parent_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};
