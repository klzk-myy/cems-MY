<?php

/**
 * Consolidated Migration: Accounting Tables
 * Replaces: 2025_03_31_000015, 2026_04_01_000002, 2026_04_01_000003,
 *          2026_04_01_000004, 2026_04_03_000005, 2026_04_03_000007,
 *          2026_04_03_000008, 2026_04_05_000001_enhance_journal_entries_table,
 *          2026_04_05_000003_add_off_balance_account_type,
 *          2026_04_05_000005_enhance_chart_of_accounts,
 *          2026_04_05_070000_add_check_fields_to_bank_reconciliations,
 *          2026_04_05_000002_create_departments,
 *          2026_04_05_000003_create_cost_centers,
 *          2026_04_05_000004_create_fiscal_years
 *
 * Creates: chart_of_accounts, departments, cost_centers, fiscal_years,
 *          accounting_periods, journal_entries, journal_lines,
 *          account_ledger, bank_reconciliations
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts table
        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->string('account_code', 20)->primary();
                $table->string('account_name', 255);
                $table->enum('account_type', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Off-Balance']);
                $table->string('account_class', 50)->nullable();
                $table->string('parent_code', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('allow_journal')->default(true);
                $table->foreignId('cost_center_id')->nullable();
                $table->foreignId('department_id')->nullable();
                $table->timestamps();
                $table->foreign('parent_code')->references('account_code')->on('chart_of_accounts');
                $table->index('account_type');
            });

            // Insert base accounts
            DB::table('chart_of_accounts')->insert([
                ['account_code' => '1000', 'account_name' => 'Cash - MYR', 'account_type' => 'Asset', 'is_active' => true],
                ['account_code' => '1100', 'account_name' => 'Cash - USD', 'account_type' => 'Asset', 'is_active' => true],
                ['account_code' => '1200', 'account_name' => 'Cash - EUR', 'account_type' => 'Asset', 'is_active' => true],
                ['account_code' => '2000', 'account_name' => 'Foreign Currency Inventory', 'account_type' => 'Asset', 'is_active' => true],
                ['account_code' => '4000', 'account_name' => 'Revenue - Forex', 'account_type' => 'Revenue', 'is_active' => true],
                ['account_code' => '5000', 'account_name' => 'Revenue - Forex Trading', 'account_type' => 'Revenue', 'is_active' => true],
                ['account_code' => '5100', 'account_name' => 'Revenue - Revaluation Gain', 'account_type' => 'Revenue', 'is_active' => true],
                ['account_code' => '6000', 'account_name' => 'Expense - Forex Loss', 'account_type' => 'Expense', 'is_active' => true],
            ]);
        }

        // Departments table
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });
        }

        // Cost centers table
        if (! Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });
        }

        // Fiscal years table
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->string('year_code', 10)->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_closed')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->index('is_closed');
            });
        }

        // Accounting periods table
        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('period_code', 10)->unique();
                $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['Open', 'Closed', 'Locked'])->default('Open');
                $table->boolean('is_adjustment_period')->default(false);
                $table->timestamps();
                $table->index('status');
                $table->index(['start_date', 'end_date']);
            });
        }

        // Journal entries table (full schema)
        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number', 20)->unique()->nullable();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->date('entry_date')->nullable(false);
                $table->string('reference_type', 50)->nullable(false);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable(false);
                $table->enum('status', ['Draft', 'Pending', 'Posted', 'Reversed'])->default('Posted');
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->foreignId('posted_by')->nullable(false)->constrained('users');
                $table->timestamp('posted_at')->useCurrent();
                $table->foreignId('reversed_by')->nullable()->constrained('users');
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('cost_center_id')->nullable();
                $table->foreignId('department_id')->nullable();
                $table->timestamps();
                $table->index('entry_date');
                $table->index(['reference_type', 'reference_id']);
                $table->index('status');
                $table->index('entry_number');
            });
        }

        // Journal lines table
        if (! Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
                $table->string('account_code', 20);
                $table->decimal('debit_amount', 18, 4)->default(0);
                $table->decimal('credit_amount', 18, 4)->default(0);
                $table->string('description')->nullable();
                $table->foreignId('cost_center_id')->nullable();
                $table->foreignId('department_id')->nullable();
                $table->timestamps();
                $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
                $table->index(['journal_entry_id', 'account_code']);
            });
        }

        // Account ledger table
        if (! Schema::hasTable('account_ledger')) {
            Schema::create('account_ledger', function (Blueprint $table) {
                $table->id();
                $table->string('account_code', 20);
                $table->date('transaction_date');
                $table->string('entry_type', 20);
                $table->unsignedBigInteger('entry_id');
                $table->decimal('debit_amount', 18, 4)->default(0);
                $table->decimal('credit_amount', 18, 4)->default(0);
                $table->decimal('running_balance', 18, 4)->default(0);
                $table->string('reference_type', 50)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('cost_center_id')->nullable();
                $table->foreignId('department_id')->nullable();
                $table->timestamps();
                $table->foreign('account_code')->references('account_code')->on('chart_of_accounts');
                $table->index(['account_code', 'transaction_date']);
                $table->index('running_balance');
            });
        }

        // Bank reconciliations table
        if (! Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('account_number', 30);
                $table->string('bank_name', 100);
                $table->date('statement_date');
                $table->decimal('statement_balance', 18, 4);
                $table->decimal('book_balance', 18, 4);
                $table->decimal('difference', 18, 4)->default(0);
                $table->text('notes')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('prepared_by')->nullable()->constrained('users');
                $table->foreignId('reviewed_by')->nullable()->constrained('users');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['account_number', 'statement_date']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('account_ledger');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('chart_of_accounts');
    }
};
