<?php

/**
 * Consolidated Migration: Core Entity Tables
 * Replaces: 2025_03_31_000001 through 2025_03_31_000010
 *
 * Creates: users, customers, currencies, exchange_rates, transactions,
 *          system_logs, sanction_lists, sanction_entries, flagged_transactions,
 *          high_risk_countries
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table (combined with mfa_verified_at)
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('username', 50)->unique();
                $table->string('email', 255)->unique();
                $table->string('password_hash', 255);
                $table->enum('role', ['teller', 'manager', 'compliance_officer', 'admin'])->default('teller');
                $table->boolean('mfa_enabled')->default(false);
                $table->text('mfa_secret')->nullable();
                $table->timestamp('mfa_verified_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->index('role');
                $table->index('is_active');
            });
        }

        // Customers table
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('full_name', 255);
                $table->enum('id_type', ['MyKad', 'Passport', 'Others']);
                $table->binary('id_number_encrypted');
                $table->string('nationality', 100);
                $table->date('date_of_birth');
                $table->text('address')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 255)->nullable();
                $table->boolean('pep_status')->default(false);
                $table->integer('risk_score')->default(0);
                $table->enum('risk_rating', ['Low', 'Medium', 'High'])->default('Low');
                $table->timestamp('risk_assessed_at')->nullable();
                $table->timestamp('last_transaction_at')->nullable();
                $table->timestamp('sanctions_screened_at')->nullable();
                $table->timestamps();
                $table->index('id_type');
                $table->index('nationality');
                $table->index('pep_status');
                $table->index('risk_rating');
                $table->index('last_transaction_at');
            });
        }

        // Currencies table with seed data
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->string('code', 3)->primary();
                $table->string('name', 100);
                $table->string('symbol', 10)->nullable();
                $table->tinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });

            DB::table('currencies')->insert([
                ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0, 'is_active' => true],
                ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'decimal_places' => 2, 'is_active' => true],
                ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimal_places' => 2, 'is_active' => true],
            ]);
        }

        // Exchange rates table
        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->string('currency_code', 3);
                $table->decimal('rate_buy', 18, 6);
                $table->decimal('rate_sell', 18, 6);
                $table->string('source', 50);
                $table->timestamp('fetched_at');
                $table->timestamps();
                $table->foreign('currency_code')->references('code')->on('currencies');
                $table->index(['currency_code', 'fetched_at']);
            });
        }

        // Transactions table (full schema with all enhancements)
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->foreignId('customer_id')->constrained();
                $table->foreignId('user_id')->constrained();
                $table->string('till_id', 50)->default('MAIN');
                $table->enum('type', ['Buy', 'Sell']);
                $table->string('currency_code', 3);
                $table->decimal('amount_local', 18, 4);
                $table->decimal('amount_foreign', 18, 4);
                $table->decimal('rate', 18, 6);
                $table->string('base_rate', 20)->nullable();
                $table->boolean('rate_override')->default(false);
                $table->integer('rate_override_approved_by')->nullable();
                $table->timestamp('rate_override_approved_at')->nullable();
                $table->text('purpose')->nullable();
                $table->string('source_of_funds', 255)->nullable();
                $table->enum('status', ['Pending', 'Completed', 'OnHold', 'Rejected', 'Reversed', 'Cancelled'])->default('Pending');
                $table->unsignedInteger('version')->default(0);
                $table->text('hold_reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->enum('cdd_level', ['Simplified', 'Specific', 'Standard', 'Enhanced']);
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users');
                $table->text('cancellation_reason')->nullable();
                $table->foreignId('original_transaction_id')->nullable()->constrained('transactions');
                $table->boolean('is_refund')->default(false);
                $table->timestamps();
                $table->index(['customer_id', 'created_at']);
                $table->index('status');
                $table->index(['type', 'currency_code']);
                $table->index('created_at');
                $table->index('amount_local');
                $table->index('cancelled_at');
                $table->index('is_refund');
                $table->index('original_transaction_id');
                $table->index(['user_id', 'created_at', 'amount_local'], 'idx_duplicate_check');
                $table->foreign('currency_code')->references('code')->on('currencies');
            });
        }

        // System logs table (full schema with all enhancements)
        if (! Schema::hasTable('system_logs')) {
            Schema::create('system_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained();
                $table->string('action', 100);
                $table->text('description')->nullable();
                $table->enum('severity', ['INFO', 'WARNING', 'ERROR', 'CRITICAL'])->default('INFO');
                $table->string('entity_type', 50)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id', 255)->nullable();
                $table->string('previous_hash', 64)->nullable();
                $table->string('entry_hash', 64)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'action']);
                $table->index(['entity_type', 'entity_id']);
                $table->index('created_at');
                $table->index('action', 'idx_system_logs_action');
                $table->index('severity', 'idx_system_logs_severity');
                $table->index('entity_type', 'idx_system_logs_entity_type');
                $table->index(['user_id', 'created_at'], 'idx_system_logs_user_date');
                $table->index(['action', 'created_at'], 'idx_system_logs_action_date');
                $table->index(['severity', 'created_at'], 'idx_system_logs_severity_date');
                $table->index('session_id');
                $table->index('previous_hash');
            });
        }

        // Sanction lists table
        if (! Schema::hasTable('sanction_lists')) {
            Schema::create('sanction_lists', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->enum('list_type', ['UNSCR', 'MOHA', 'Internal']);
                $table->string('source_file', 255)->nullable();
                $table->foreignId('uploaded_by')->constrained('users');
                $table->boolean('is_active')->default(true);
                $table->timestamp('uploaded_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamps();
                $table->index('list_type');
                $table->index('is_active');
            });
        }

        // Sanction entries table
        if (! Schema::hasTable('sanction_entries')) {
            Schema::create('sanction_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('list_id')->constrained('sanction_lists')->onDelete('cascade');
                $table->string('entity_name', 255);
                $table->enum('entity_type', ['Individual', 'Entity'])->default('Individual');
                $table->text('aliases')->nullable();
                $table->string('nationality', 100)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();
                $table->index('list_id');
                $table->index('entity_name');
            });
        }

        // Flagged transactions table
        if (! Schema::hasTable('flagged_transactions')) {
            Schema::create('flagged_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('customer_id')->nullable()->constrained('customers');
                $table->enum('flag_type', [
                    'Large_Amount', 'Sanctions_Hit', 'Velocity', 'Structuring',
                    'EDD_Required', 'Pep_Status', 'Sanction_Match', 'High_Risk_Customer',
                    'Unusual_Pattern', 'Manual_Review', 'High_Risk_Country',
                    'Round_Amount', 'Profile_Deviation', 'Aml_Rule_Triggered', 'Counterfeit_Currency',
                ]);
                $table->text('flag_reason');
                $table->enum('status', ['Open', 'Under_Review', 'Resolved', 'Rejected'])->default('Open');
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->foreignId('reviewed_by')->nullable()->constrained('users');
                $table->text('notes')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index('transaction_id');
                $table->index('status');
                $table->index('assigned_to');
                $table->index('flag_type');
            });
        }

        // High risk countries table
        if (! Schema::hasTable('high_risk_countries')) {
            Schema::create('high_risk_countries', function (Blueprint $table) {
                $table->string('country_code', 2)->primary();
                $table->string('country_name', 100);
                $table->enum('risk_level', ['High', 'Grey']);
                $table->string('source', 50);
                $table->date('list_date');
                $table->timestamps();
                $table->index('risk_level');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('high_risk_countries');
        Schema::dropIfExists('flagged_transactions');
        Schema::dropIfExists('sanction_entries');
        Schema::dropIfExists('sanction_lists');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('users');
    }
};
