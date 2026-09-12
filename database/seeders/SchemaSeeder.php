<?php

namespace Database\Seeders;

use App\Enums\AmlRuleType;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SchemaSeeder — recreates the full CEMS-MY database schema.
 *
 * Generated deterministically from the migration ground truth (85 tables).
 * This seeder is the single source of truth for the schema so the
 * project no longer depends on the migrations directory. Running it drops
 * and recreates every table, mirroring a migrate:fresh reset.
 */
class SchemaSeeder extends Seeder
{
    /**
     * Run the seeder through the application container (used by tests and
     * reset flows that are not inside a normal db:seed command context).
     */
    public static function seedNow(Application $app): void
    {
        $seeder = $app->make(self::class);
        $seeder->run();
    }

    public function run(): void
    {
        $tables = [
            'branches',
            'users',
            'fiscal_years',
            'accounting_periods',
            'journal_entries',
            'chart_of_accounts',
            'account_ledger',
            'adverse_media_entries',
            'adverse_media_import_logs',
            'compliance_findings',
            'customers',
            'currencies',
            'transactions',
            'flagged_transactions',
            'compliance_cases',
            'alerts',
            'aml_rules',
            'audit_trails',
            'backup_logs',
            'bank_reconciliations',
            'branch_closure_workflows',
            'branch_pools',
            'budgets',
            'cache',
            'cache_locks',
            'compliance_case_documents',
            'compliance_case_links',
            'compliance_case_notes',
            'departments',
            'cost_centers',
            'counters',
            'teller_allocations',
            'counter_sessions',
            'counter_handovers',
            'currency_positions',
            'customer_behavioral_baselines',
            'customer_documents',
            'customer_notes',
            'customer_relations',
            'customer_risk_history',
            'customer_risk_profiles',
            'device_computations',
            'edd_questionnaire_templates',
            'enhanced_diligence_records',
            'edd_document_requests',
            'edd_templates',
            'emergency_closures',
            'expenses',
            'exchange_rate_histories',
            'exchange_rates',
            'failed_jobs',
            'high_risk_countries',
            'job_batches',
            'jobs',
            'journal_lines',
            'mfa_recovery_codes',
            'notifications',
            'password_histories',
            'password_reset_tokens',
            'pep_approval_requests',
            'personal_access_tokens',
            'report_schedules',
            'report_runs',
            'reports_generated',
            'revaluation_entries',
            'risk_score_snapshots',
            'sanction_lists',
            'sanction_entries',
            'sanction_import_logs',
            'sanctions_analyses',
            'screening_results',
            'setup_state',
            'stock_reservations',
            'stock_transfers',
            'stock_transfer_items',
            'str_reports',
            'system_alerts',
            'system_health_checks',
            'system_logs',
            'test_results',
            'threshold_audits',
            'till_balances',
            'transaction_confirmations',
            'transaction_errors',
            'transaction_imports',
            'user_notification_preferences',
        ];

        Schema::disableForeignKeyConstraints();

        foreach (array_reverse($tables) as $table) {
            Schema::dropIfExists($table);
        }

        // Legacy installs retain the framework's migration bookkeeping table.
        // The migrations directory is retired, so drop it if it is present;
        // SchemaSeeder does not recreate it.
        Schema::dropIfExists('migrations');

        Schema::enableForeignKeyConstraints();

        $this->createSchema($tables);
        $this->seedReferenceData();
    }

    /**
     * Reference rows that the historical migrations inserted on a fresh
     * install (base currencies, base chart of accounts, and the surviving
     * AML rule after the legacy-rule cleanup). Keeping this here means the
     * seeder produces the exact same data as the retired migrations.
     */
    protected function seedReferenceData(): void
    {
        foreach ($this->baseCurrencies() as $currency) {
            DB::table('currencies')->updateOrInsert(['code' => $currency['code']], $currency);
        }

        foreach ($this->baseAccounts() as $account) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['account_code' => $account['account_code']],
                $account
            );
        }

        // Only the AML rule whose rule_type survives the legacy cleanup
        // (threshold/aggregation rows were removed in 2026_09_09_100003).
        DB::table('aml_rules')->updateOrInsert(
            ['rule_code' => 'HIGH_RISK_COUNTRY'],
            $this->highRiskCountryRule()
        );

        // Mirror 2026_09_09_100003_delete_legacy_aml_rules: remove any row
        // whose rule_type is not a known AmlRuleType value.
        DB::table('aml_rules')
            ->whereNotIn('rule_type', AmlRuleType::values())
            ->delete();

        // Mirror DatabaseSeeder's post-schema step so seedNow() callers (the
        // whole test suite) see the same enum-backed chart of accounts as a
        // real `db:seed`. The baseAccounts() subset above stays first because
        // the retired migrations inserted it; the enum seeder then upserts
        // the full AccountCode set keyed by code.
        (new EnhancedChartOfAccountsSeeder)->run();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function baseCurrencies(): array
    {
        return [
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2, 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function baseAccounts(): array
    {
        return [
            ['account_code' => '1000', 'account_name' => 'Cash - MYR', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '1100', 'account_name' => 'Cash - USD', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '1200', 'account_name' => 'Cash - EUR', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '2000', 'account_name' => 'Foreign Currency Inventory', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '4000', 'account_name' => 'Revenue - Forex', 'account_type' => 'Revenue', 'is_active' => true],
            ['account_code' => '5000', 'account_name' => 'Revenue - Forex Trading', 'account_type' => 'Revenue', 'is_active' => true],
            ['account_code' => '5100', 'account_name' => 'Revenue - Revaluation Gain', 'account_type' => 'Revenue', 'is_active' => true],
            ['account_code' => '6000', 'account_name' => 'Expense - Forex Loss', 'account_type' => 'Expense', 'is_active' => true],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function highRiskCountryRule(): array
    {
        return [
            'rule_name' => 'High Risk Country',
            'description' => 'Flag transactions involving high-risk countries',
            'is_active' => true,
            'conditions' => json_encode(['risk_levels' => ['High', 'Grey']]),
            'rule_type' => AmlRuleType::Geographic->value,
            'action' => 'flag',
            'risk_score' => 40,
            'created_by' => null,
        ];
    }

    protected function createSchema(array $tables): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('branch');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Malaysia');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false);
            $table->decimal('petty_cash_float', 18, 4)->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->index('code', 'branches_code_index');
            $table->unique('code', 'branches_code_unique');
            $table->index('deleted_at', 'branches_deleted_at_index');
            $table->index(['is_active', 'type'], 'branches_is_active_type_index');
            $table->index('parent_id', 'branches_parent_id_index');
            $table->foreign('parent_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('email');
            $table->string('password_hash');
            $table->string('role')->default('teller');
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('mfa_verified_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->text('notification_preferences')->nullable();
            $table->unique('email', 'users_email_unique');
            $table->index('is_active', 'users_is_active_index');
            $table->index('role', 'users_role_index');
            $table->unique('username', 'users_username_unique');
            $table->index('deleted_at', 'users_deleted_at_index');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_code');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['Open', 'Closed', 'Archived'])->default('Open');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('year_code', 'fiscal_years_year_code_unique');
            $table->index('closed_by', 'idx_eb6b84ef9bca');
            $table->foreign('closed_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_type')->default('month');
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->index('end_date', 'accounting_periods_end_date_index');
            $table->unique('period_code', 'accounting_periods_period_code_unique');
            $table->index('start_date', 'accounting_periods_start_date_index');
            $table->index('status', 'accounting_periods_status_index');
            $table->index(['closed_by', 'fiscal_year_id'], 'idx_b8bd21b18fbb');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('reference_type', 50);
            $table->bigInteger('reference_id')->unsigned()->nullable();
            $table->text('description');
            $table->string('status', 20)->default('Posted')->nullable();
            $table->bigInteger('posted_by')->unsigned()->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->bigInteger('reversed_by')->unsigned()->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->string('entry_number', 50)->nullable();
            $table->bigInteger('cost_center_id')->unsigned()->nullable();
            $table->bigInteger('department_id')->unsigned()->nullable();
            $table->bigInteger('branch_id')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('period_id')->unsigned()->nullable();
            $table->index('entry_date', 'idx_journal_entries_entry_date');
            $table->index('period_id', 'idx_journal_entries_period_id');
            $table->index('status', 'idx_journal_entries_status');
            $table->index('created_by', 'idx_journal_entries_created_by');
            $table->index(['period_id', 'status'], 'idx_journal_entries_period_status');
            $table->index(['period_id', 'approved_by', 'created_by', 'reversed_by', 'posted_by'], 'idx_360ab721c158');
            $table->foreign('period_id')->references('id')->on('accounting_periods');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('reversed_by')->references('id')->on('users');
            $table->foreign('posted_by')->references('id')->on('users');
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->string('account_code')->primary();
            $table->string('account_name');
            $table->enum('account_type', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Off-Balance']);
            $table->string('parent_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('account_class')->nullable();
            $table->boolean('allow_journal')->default(true);
            $table->integer('cost_center_id')->nullable();
            $table->integer('department_id')->nullable();
            $table->string('normal_balance')->nullable();
            $table->index('account_type', 'chart_of_accounts_account_type_index');
            $table->foreign('parent_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::create('account_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('account_code');
            $table->date('entry_date');
            $table->unsignedBigInteger('journal_entry_id');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->decimal('running_balance', 18, 4);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->index(['account_code', 'entry_date'], 'account_ledger_account_code_entry_date_index');
            $table->index('journal_entry_id', 'account_ledger_journal_entry_id_index');
            $table->index('branch_id', 'account_ledger_branch_id_index');
            $table->index(['account_code', 'entry_date'], 'idx_account_ledger_account_entry');
            $table->index('entry_date', 'idx_account_ledger_entry_date');
            $table->index('journal_entry_id', 'idx_account_ledger_journal_entry');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::create('adverse_media_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->nullable();
            $table->string('alias')->nullable();
            $table->string('article_title');
            $table->string('source');
            $table->string('url')->nullable();
            $table->text('snippet')->nullable();
            $table->date('published_at')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->string('record_hash');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('name', 'adverse_media_entries_name_index');
            $table->index(['is_active', 'severity'], 'adverse_media_entries_is_active_severity_index');
            $table->unique('record_hash', 'adverse_media_entries_record_hash_unique');
        });

        Schema::create('adverse_media_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('imported_file')->nullable();
            $table->timestamp('imported_at');
            $table->integer('records_added')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->enum('status', ['success', 'partial', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('manual');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('imported_at', 'adverse_media_import_logs_imported_at_index');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('compliance_findings', function (Blueprint $table) {
            $table->id();
            $table->string('finding_type');
            $table->string('severity');
            $table->string('subject_type');
            $table->integer('subject_id');
            $table->text('details')->nullable();
            $table->string('status')->default('New');
            $table->timestamp('generated_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['finding_type', 'status'], 'compliance_findings_finding_type_status_index');
            $table->index(['subject_type', 'subject_id'], 'compliance_findings_subject_type_subject_id_index');
            $table->index(['severity', 'status'], 'compliance_findings_severity_status_index');
            $table->index('generated_at', 'compliance_findings_generated_at_index');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('id_type');
            $table->binary('id_number_encrypted');
            $table->string('nationality');
            $table->date('date_of_birth');
            $table->text('address')->nullable();
            $table->text('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('pep_status')->default(false);
            $table->integer('risk_score')->default(0);
            $table->string('risk_rating')->default('Low');
            $table->timestamp('risk_assessed_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sanction_hit')->default(false);
            $table->string('cdd_level')->default('Simplified');
            $table->string('occupation')->nullable();
            $table->string('employer_name')->nullable();
            $table->text('employer_address')->nullable();
            $table->decimal('annual_volume_estimate', 20, 4)->nullable();
            $table->timestamp('sanctions_screened_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('customer_type')->default('individual');
            $table->boolean('is_pep_associate')->default(false);
            $table->string('id_number_hash')->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->string('freeze_reason')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->boolean('transactions_blocked')->default(false);
            $table->string('rejection_reason')->nullable();
            $table->timestamp('pep_role_ended_at')->nullable();
            $table->string('current_role_domain')->nullable();
            $table->string('former_pep_domain')->nullable();
            $table->string('pep_type')->nullable();
            $table->string('phone_hash')->nullable();
            $table->string('closure_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('dormant_at')->nullable();
            $table->index('cdd_level', 'customers_cdd_level_index');
            $table->index('customer_type', 'customers_customer_type_index');
            $table->index('date_of_birth', 'customers_date_of_birth_index');
            $table->index('deleted_at', 'customers_deleted_at_index');
            $table->index('full_name', 'customers_full_name_index');
            $table->index('id_type', 'customers_id_type_index');
            $table->index('is_active', 'customers_is_active_index');
            $table->index('last_transaction_at', 'customers_last_transaction_at_index');
            $table->index('nationality', 'customers_nationality_index');
            $table->index('pep_status', 'customers_pep_status_index');
            $table->index('risk_rating', 'customers_risk_rating_index');
            $table->index(['risk_rating', 'last_transaction_at'], 'customers_risk_transaction_idx');
            $table->index('sanction_hit', 'customers_sanction_hit_index');
            $table->unique('id_number_hash', 'customers_id_number_hash_unique');
            $table->unique('phone_hash', 'customers_phone_hash_unique');
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->integer('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index('is_active', 'currencies_is_active_index');
            $table->index('deleted_at', 'currencies_deleted_at_index');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('till_id')->default('MAIN');
            $table->string('type');
            $table->string('currency_code');
            $table->decimal('amount_local', 18, 4);
            $table->decimal('amount_foreign', 18, 4);
            $table->decimal('rate', 18, 6);
            $table->text('purpose')->nullable();
            $table->string('source_of_funds')->nullable();
            $table->string('status')->default('Draft');
            $table->text('hold_reason')->nullable();
            $table->unsignedBigInteger('compliance_cleared_by')->nullable();
            $table->timestamp('compliance_cleared_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('cdd_level');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('original_transaction_id')->nullable();
            $table->boolean('is_refund')->default(false);
            $table->string('idempotency_key')->nullable();
            $table->integer('version')->default(0);
            $table->decimal('base_rate', 18, 6)->nullable();
            $table->boolean('rate_override')->default(false);
            $table->integer('rate_override_approved_by')->nullable();
            $table->timestamp('rate_override_approved_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->text('transition_history')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('deferred_journal_entry_id')->nullable();
            $table->timestamp('journal_entries_created_at')->nullable();
            $table->boolean('has_deferred_accounting')->default(false);
            $table->boolean('approval_sync_failed')->default(false);
            $table->timestamp('approval_sync_failed_at')->nullable();
            $table->text('approval_sync_error')->nullable();
            $table->string('counterparty_country')->nullable();
            $table->integer('counter_id')->nullable();
            $table->string('source_of_wealth')->nullable();
            $table->boolean('is_dlq')->default(false);
            // Position-state snapshot captured when the position mutation is applied,
            // so reversals can restore the exact prior cost basis (plan §1.3).
            $table->decimal('prev_quantity', 18, 4)->nullable();
            $table->decimal('prev_average_cost', 18, 6)->nullable();
            $table->index(['user_id', 'created_at', 'amount_local'], 'idx_duplicate_check');
            $table->index('amount_local', 'transactions_amount_local_index');
            $table->index('approval_sync_failed', 'transactions_approval_sync_failed_index');
            $table->index('approved_by', 'transactions_approved_by_index');
            $table->index('compliance_cleared_by', 'transactions_compliance_cleared_by_index');
            $table->index(['branch_id', 'created_at'], 'transactions_branch_created_idx');
            $table->index('branch_id', 'transactions_branch_id_fk_index');
            $table->index('branch_id', 'transactions_branch_id_index');
            $table->index('cancelled_at', 'transactions_cancelled_at_index');
            $table->index('created_at', 'transactions_created_at_index');
            $table->index(['currency_code', 'created_at'], 'transactions_currency_created_idx');
            $table->index(['customer_id', 'created_at'], 'transactions_customer_created_idx');
            $table->index(['customer_id', 'created_at'], 'transactions_customer_id_created_at_index');
            $table->index('customer_id', 'transactions_customer_id_index');
            $table->index('deleted_at', 'transactions_deleted_at_index');
            $table->unique('idempotency_key', 'transactions_idempotency_key_unique');
            $table->index('is_refund', 'transactions_is_refund_index');
            $table->index('original_transaction_id', 'transactions_original_transaction_id_index');
            $table->index(['status', 'created_at'], 'transactions_status_created_idx');
            $table->index('status', 'transactions_status_index');
            $table->index(['type', 'currency_code'], 'transactions_type_currency_code_index');
            $table->index(['user_id', 'created_at'], 'transactions_user_created_idx');
            $table->index('user_id', 'transactions_user_id_index');
            $table->index('is_dlq', 'transactions_is_dlq_index');
            $table->index(['branch_id', 'created_at'], 'transactions_branch_created');
            $table->index(['branch_id', 'status', 'created_at'], 'idx_transactions_branch_status_date');
            $table->index(['branch_id', 'created_at'], 'transactions_branch_id_created_at_index');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('cancelled_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('original_transaction_id')->references('id')->on('transactions')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('deferred_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('flagged_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->enum('flag_type', ['Large_Amount', 'Sanctions_Hit', 'Velocity', 'Structuring', 'EDD_Required', 'Pep_Status', 'Sanction_Match', 'High_Risk_Customer', 'Unusual_Pattern', 'Manual_Review', 'High_Risk_Country', 'Round_Amount', 'Profile_Deviation', 'Aml_Rule_Triggered', 'Counterfeit_Currency']);
            $table->text('flag_reason');
            $table->enum('status', ['Open', 'Under_Review', 'Resolved', 'Rejected'])->default('Open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('severity')->nullable();
            $table->index('transaction_id', 'flagged_transactions_transaction_id_index');
            $table->index('status', 'flagged_transactions_status_index');
            $table->index('assigned_to', 'flagged_transactions_assigned_to_index');
            $table->index('flag_type', 'flagged_transactions_flag_type_index');
            $table->index('status', 'flagged_trans_status_idx');
            $table->index('created_at', 'flagged_trans_created_idx');
            $table->index('flag_type', 'flagged_trans_flag_type_idx');
            $table->index(['flag_type', 'created_at'], 'flagged_transactions_flag_type_created_idx');
            $table->index(['status', 'created_at'], 'flagged_transactions_status_created_idx');
            $table->index(['status', 'created_at'], 'flagged_transactions_status_date_idx');
            $table->index(['flag_type', 'created_at'], 'flagged_transactions_flag_type_date_idx');
            $table->index(['reviewed_by', 'customer_id'], 'idx_f024fc6e952e');
            $table->index(['status', 'flag_type'], 'flagged_transactions_status_flag_type');
            $table->index(['status', 'created_at'], 'idx_flagged_transactions_status_date');
            $table->index(['status', 'flag_type'], 'flagged_transactions_status_flag_type_index');
            $table->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
        });

        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number');
            $table->string('case_type');
            $table->string('status')->default('Open');
            $table->string('severity');
            $table->string('priority');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('primary_flag_id')->nullable();
            $table->unsignedBigInteger('primary_finding_id')->nullable();
            $table->unsignedBigInteger('assigned_to');
            $table->text('case_summary')->nullable();
            $table->timestamp('sla_deadline');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('metadata')->nullable();
            $table->string('created_via');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index('case_number', 'compliance_cases_case_number_idx');
            $table->index(['case_type', 'status'], 'compliance_cases_case_type_status_index');
            $table->index(['severity', 'status'], 'compliance_cases_severity_status_index');
            $table->index('customer_id', 'compliance_cases_customer_id_index');
            $table->index('assigned_to', 'compliance_cases_assigned_to_index');
            $table->index('sla_deadline', 'compliance_cases_sla_deadline_index');
            $table->index(['primary_finding_id', 'primary_flag_id'], 'idx_dd85db6c55c7');
            $table->unique('case_number', 'compliance_cases_case_number_unique');
            $table->foreign('assigned_to')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('primary_finding_id')->references('id')->on('compliance_findings')->restrictOnDelete();
            $table->foreign('primary_flag_id')->references('id')->on('flagged_transactions')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flagged_transaction_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->string('type');
            $table->string('priority');
            $table->integer('risk_score')->default(0);
            $table->text('reason')->nullable();
            $table->string('source')->default('System');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('status')->default('Open');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['priority', 'case_id'], 'alerts_priority_case_id_index');
            $table->index(['customer_id', 'created_at'], 'alerts_customer_id_created_at_index');
            $table->index('assigned_to', 'alerts_assigned_to_index');
            $table->index('status', 'alerts_status_index');
            $table->index(['assigned_to', 'case_id', 'status'], 'alerts_composite_idx');
            $table->index(['case_id', 'flagged_transaction_id'], 'idx_ca3f04794c4d');
            // One alert per flag: makes the flag→alert bridge race-free (MySQL和SQLite both
            // permit multiple NULLs here so case-created alerts are unaffected).
            $table->unique('flagged_transaction_id', 'alerts_flagged_transaction_unique');
            $table->index(['priority', 'status'], 'alerts_priority_status');
            $table->index(['priority', 'status'], 'alerts_priority_status_index');
            $table->foreign('case_id')->references('id')->on('compliance_cases')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('flagged_transaction_id')->references('id')->on('flagged_transactions')->nullOnDelete();
        });

        Schema::create('aml_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code');
            $table->string('rule_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('conditions')->nullable();
            $table->string('rule_type')->nullable();
            $table->string('action')->default('flag');
            $table->integer('risk_score')->default(0);
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('is_active', 'aml_rules_is_active_index');
            $table->index('rule_type', 'aml_rules_rule_type_index');
            $table->index('action', 'aml_rules_action_index');
            $table->index('risk_score', 'aml_rules_risk_score_index');
            $table->unique('rule_code', 'aml_rules_rule_code_unique');
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->integer('auditable_id');
            $table->string('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['auditable_type', 'auditable_id'], 'audit_trails_auditable_type_auditable_id_index');
            $table->index('action', 'audit_trails_action_index');
            $table->index('user_id', 'audit_trails_user_id_index');
            $table->index(['ip_address', 'created_at'], 'idx_audit_trails_ip_date');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('backup_name');
            $table->string('backup_type');
            $table->string('disk');
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('checksum')->nullable();
            $table->boolean('encryption_status')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('verification_status')->nullable();
            $table->text('verification_error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('status', 'backup_logs_status_index');
            $table->index('backup_type', 'backup_logs_backup_type_index');
            $table->index('disk', 'backup_logs_disk_index');
            $table->index('started_at', 'backup_logs_started_at_index');
            $table->index('completed_at', 'backup_logs_completed_at_index');
            $table->index(['status', 'started_at'], 'backup_logs_status_started_at_index');
            $table->index(['backup_type', 'status'], 'backup_logs_backup_type_status_index');
            $table->index('user_id', 'idx_5d32726c14a0');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('account_code');
            $table->date('statement_date');
            $table->string('reference')->nullable();
            $table->text('description');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->enum('status', ['unmatched', 'matched', 'exception'])->default('unmatched');
            $table->unsignedBigInteger('matched_to_journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('check_number')->nullable();
            $table->date('check_date')->nullable();
            $table->enum('check_status', ['issued', 'presented', 'cleared', 'returned', 'stopped'])->nullable();
            $table->string('check_payee')->nullable();
            $table->index(['account_code', 'statement_date'], 'bank_reconciliations_account_code_statement_date_index');
            $table->index('status', 'bank_reconciliations_status_index');
            $table->index('check_number', 'bank_reconciliations_check_number_index');
            $table->index('check_status', 'bank_reconciliations_check_status_index');
            $table->index(['created_by', 'matched_to_journal_entry_id'], 'idx_f819fd05c8d8');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('matched_to_journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::create('branch_closure_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('initiated_by');
            $table->string('status')->default('initiated');
            $table->text('checklist')->nullable();
            $table->timestamp('settlement_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['branch_id', 'status'], 'branch_closure_workflows_branch_id_status_index');
            $table->index(['status', 'created_at'], 'branch_closure_workflows_status_created_at_index');
            $table->foreign('initiated_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('branch_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('currency_code');
            $table->decimal('available_balance', 20, 4)->default(0);
            $table->decimal('allocated_balance', 20, 4)->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['branch_id', 'currency_code'], 'branch_pools_branch_id_currency_code_unique');
            $table->index(['branch_id', 'currency_code'], 'branch_pools_branch_id_currency_code_index');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('account_code');
            $table->string('period_code');
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['account_code', 'period_code'], 'budgets_account_code_period_code_unique');
            $table->index('period_code', 'budgets_period_code_index');
            $table->index('created_by', 'idx_0ed2bf17c865');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');
            $table->index('expiration', 'cache_expiration_index');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
            $table->index('expiration', 'cache_locks_expiration_index');
        });

        Schema::create('compliance_case_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['verified_by', 'uploaded_by', 'case_id'], 'idx_ff6e463f546e');
            $table->foreign('verified_by')->references('id')->on('users');
            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->foreign('case_id')->references('id')->on('compliance_cases')->cascadeOnDelete();
        });

        Schema::create('compliance_case_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('linked_type');
            $table->integer('linked_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['linked_type', 'linked_id'], 'compliance_case_links_linked_type_linked_id_index');
            $table->index('case_id', 'idx_ec72288ec478');
            $table->foreign('case_id')->references('id')->on('compliance_cases')->cascadeOnDelete();
        });

        Schema::create('compliance_case_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('author_id');
            $table->string('note_type');
            $table->text('content');
            $table->boolean('is_internal')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['author_id', 'case_id'], 'idx_8360d5811279');
            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('case_id')->references('id')->on('compliance_cases')->cascadeOnDelete();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('code', 'departments_code_unique');
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('code', 'cost_centers_code_unique');
            $table->index('department_id', 'idx_3bb01e72aaa8');
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('assigned_teller_id')->nullable();
            $table->index('branch_id', 'counters_branch_id_index');
            $table->unique('code', 'counters_code_unique');
            $table->index('deleted_at', 'counters_deleted_at_index');
            $table->index('status', 'counters_status_index');
            $table->foreign('assigned_teller_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('teller_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('counter_id')->nullable();
            $table->string('currency_code');
            $table->decimal('allocated_amount', 20, 4);
            $table->decimal('current_balance', 20, 4);
            $table->decimal('requested_amount', 20, 4);
            $table->decimal('daily_limit_myr', 20, 4)->default(0);
            $table->decimal('daily_used_myr', 20, 4)->default(0);
            $table->enum('status', ['pending', 'approved', 'active', 'returned', 'closed', 'auto_returned', 'rejected'])->default('pending');
            $table->date('session_date');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->integer('rejected_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->index(['user_id', 'currency_code', 'session_date', 'status'], 'teller_alloc_user_currency_date_status_idx');
            $table->index(['counter_id', 'session_date'], 'teller_alloc_counter_date_idx');
            $table->index(['branch_id', 'session_date'], 'teller_alloc_branch_date_idx');
            $table->index('approved_by', 'idx_40a1c4772e24');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('counter_id')->references('id')->on('counters')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('counter_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');
            $table->unsignedBigInteger('user_id');
            $table->date('session_date');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('teller_allocation_id')->nullable();
            $table->decimal('requested_amount_myr', 20, 4)->nullable();
            $table->decimal('daily_limit_myr', 20, 4)->nullable();
            $table->boolean('physical_count_verified')->nullable();
            $table->string('handover_notes')->nullable();
            $table->index('closed_by', 'counter_sessions_closed_by_index');
            $table->index(['counter_id', 'session_date'], 'counter_sessions_counter_id_session_date_index');
            $table->index(['counter_id', 'status'], 'counter_sessions_counter_status_idx');
            $table->index('opened_by', 'counter_sessions_opened_by_index');
            $table->index('session_date', 'counter_sessions_session_date_index');
            $table->index('status', 'counter_sessions_status_index');
            $table->index(['user_id', 'session_date'], 'counter_sessions_user_date_idx');
            $table->index('user_id', 'counter_sessions_user_id_index');
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('opened_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('counter_id')->references('id')->on('counters')->restrictOnDelete();
            $table->foreign('teller_allocation_id')->references('id')->on('teller_allocations')->nullOnDelete();
        });

        Schema::create('counter_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_session_id');
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->unsignedBigInteger('supervisor_id');
            $table->timestamp('handover_time');
            $table->boolean('physical_count_verified')->default(true);
            $table->decimal('variance_myr', 18, 4)->default(0);
            $table->text('variance_notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('yellow_variance')->default(false);
            $table->index('counter_session_id', 'counter_handovers_counter_session_id_index');
            $table->index('from_user_id', 'counter_handovers_from_user_id_index');
            $table->index('to_user_id', 'counter_handovers_to_user_id_index');
            $table->index('supervisor_id', 'counter_handovers_supervisor_id_index');
            $table->index('handover_time', 'counter_handovers_handover_time_index');
            $table->foreign('supervisor_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('to_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('from_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('counter_session_id')->references('id')->on('counter_sessions')->restrictOnDelete();
        });

        Schema::create('currency_positions', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code');
            $table->string('branch_id')->default('HQ');
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('current_rate', 18, 6)->default(0);
            $table->decimal('current_value', 18, 4)->default(0);
            $table->decimal('unrealized_gain_loss', 18, 4)->default(0);
            $table->timestamp('last_revalued_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['currency_code', 'branch_id'], 'currency_positions_currency_code_branch_id_unique');
            $table->index('currency_code', 'currency_positions_currency_code_index');
            $table->index('branch_id', 'currency_positions_branch_id_index');
            $table->index('currency_code', 'currency_positions_currency_idx');
            $table->unique(['currency_code', 'branch_id'], 'currency_positions_currency_branch_unique');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('customer_behavioral_baselines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->text('currency_codes')->nullable();
            $table->decimal('avg_transaction_size_myr', 18, 4)->default(0);
            $table->decimal('avg_transaction_frequency', 8, 2)->default(0);
            $table->text('preferred_counter_ids')->nullable();
            $table->string('registered_location')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->integer('baseline_version')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique('customer_id', 'customer_behavioral_baselines_customer_id_unique');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('document_type');
            $table->string('file_path');
            $table->string('file_hash');
            $table->integer('file_size')->nullable();
            $table->boolean('encrypted')->default(true);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->index('customer_id', 'customer_documents_customer_id_index');
            $table->index('document_type', 'customer_documents_document_type_index');
            $table->index('uploaded_by', 'idx_1f0ab4918b04');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('note');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('customer_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('related_customer_id')->nullable();
            $table->enum('relation_type', ['spouse', 'child', 'parent', 'sibling', 'close_associate', 'business_partner', 'beneficial_owner', 'director', 'signatory', 'related_entity']);
            $table->string('related_name');
            $table->string('id_type')->nullable();
            $table->string('id_number_encrypted')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_pep')->default(false);
            $table->text('additional_info')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('engagement_level')->nullable();
            $table->text('engagement_notes')->nullable();
            $table->timestamp('engagement_assessed_at')->nullable();
            $table->index('customer_id', 'customer_relations_customer_id_index');
            $table->index(['customer_id', 'relation_type'], 'customer_relations_customer_id_relation_type_index');
            $table->index(['customer_id', 'related_customer_id', 'relation_type'], 'customer_relations_composite_idx');
            $table->index('related_customer_id', 'idx_ad4674e4e784');
            $table->foreign('related_customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('customer_risk_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->integer('old_score')->nullable();
            $table->integer('new_score');
            $table->enum('old_rating', ['Low', 'Medium', 'High'])->nullable();
            $table->enum('new_rating', ['Low', 'Medium', 'High']);
            $table->text('change_reason');
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id', 'created_at'], 'customer_risk_history_customer_id_created_at_index');
            $table->index('assessed_by', 'idx_5795c6fda673');
            $table->foreign('assessed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });

        Schema::create('customer_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->integer('risk_score')->default(20);
            $table->string('risk_tier');
            $table->text('risk_factors')->nullable();
            $table->integer('previous_score')->nullable();
            $table->timestamp('score_changed_at')->nullable();
            $table->timestamp('next_scheduled_recalculation')->nullable();
            $table->string('recalculation_trigger')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->integer('locked_by')->nullable();
            $table->string('lock_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('risk_tier', 'customer_risk_profiles_risk_tier_index');
            $table->index('risk_score', 'customer_risk_profiles_risk_score_index');
            $table->unique('customer_id', 'customer_risk_profiles_customer_id_unique');
            $table->index('risk_tier', 'customer_risk_profiles_risk_tier_idx');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('device_computations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_name')->nullable();
            $table->string('device_fingerprint');
            $table->string('ip_address')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id', 'device_fingerprint'], 'device_computations_user_id_device_fingerprint_index');
            $table->index(['user_id', 'expires_at'], 'device_computations_user_id_expires_at_index');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('edd_questionnaire_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('questions')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('enhanced_diligence_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flagged_transaction_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->string('edd_reference');
            $table->string('status')->default('Incomplete');
            $table->string('risk_level')->default('Medium');
            $table->text('source_of_funds')->nullable();
            $table->text('source_of_funds_description')->nullable();
            $table->text('source_of_funds_documents')->nullable();
            $table->text('purpose_of_transaction')->nullable();
            $table->text('business_justification')->nullable();
            $table->text('employment_status')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->text('annual_income_range')->nullable();
            $table->text('estimated_net_worth')->nullable();
            $table->text('source_of_wealth')->nullable();
            $table->text('source_of_wealth_description')->nullable();
            $table->text('additional_information')->nullable();
            $table->text('supporting_documents')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('questionnaire_responses')->nullable();
            $table->timestamp('questionnaire_completed_at')->nullable();
            $table->unsignedBigInteger('questionnaire_completed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('edd_template_id')->nullable();
            $table->index('edd_reference', 'enhanced_diligence_records_edd_reference_index');
            $table->unique('edd_reference', 'enhanced_diligence_records_edd_reference_unique');
            $table->index('status', 'enhanced_diligence_records_status_index');
            $table->index(['approved_by', 'questionnaire_completed_by', 'reviewed_by', 'customer_id', 'flagged_transaction_id'], 'idx_b50f1bb45631');
            $table->foreign('edd_template_id')->references('id')->on('edd_questionnaire_templates')->nullOnDelete();
            $table->foreign('flagged_transaction_id')->references('id')->on('flagged_transactions')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('questionnaire_completed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('edd_document_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('edd_record_id');
            $table->string('document_type');
            $table->string('status')->default('Pending');
            $table->string('file_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->integer('verified_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('edd_record_id', 'idx_18d041a74a1b');
            $table->foreign('edd_record_id')->references('id')->on('enhanced_diligence_records')->cascadeOnDelete();
        });

        Schema::create('edd_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->text('questions')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['type', 'is_active'], 'edd_templates_type_is_active_index');
            $table->index('created_by', 'idx_b81db07c9834');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('emergency_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('teller_id');
            $table->string('reason')->nullable();
            $table->timestamp('closed_at');
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('counter_id', 'emergency_closures_counter_id_index');
            $table->index('teller_id', 'emergency_closures_teller_id_index');
            $table->index(['counter_id', 'created_at'], 'emergency_closures_counter_id_created_at_index');
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('teller_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('session_id')->references('id')->on('counter_sessions')->restrictOnDelete();
            $table->foreign('counter_id')->references('id')->on('counters')->restrictOnDelete();
        });

        // Branch petty-cash expenses. Each row posts a balanced journal
        // (Dr expense account, Cr petty cash 1050) scoped to the branch.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('account_code');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 18, 4);
            $table->date('expense_date');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('branch_id', 'expenses_branch_id_index');
            $table->index('expense_date', 'expenses_expense_date_index');
            $table->index(['branch_id', 'expense_date'], 'expenses_branch_date_index');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('exchange_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code');
            $table->decimal('rate', 18, 6);
            $table->date('effective_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('spread_applied')->nullable();
            $table->index(['currency_code', 'effective_date'], 'exchange_rate_histories_currency_code_effective_date_index');
            $table->index('currency_code', 'exchange_rate_histories_currency_code_index');
            $table->index('effective_date', 'exchange_rate_histories_effective_date_index');
            $table->index(['branch_id', 'currency_code', 'effective_date'], 'erh_branch_idx');
            $table->index('created_by', 'idx_abb70be2c84e');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code');
            $table->decimal('rate_buy', 18, 6);
            $table->decimal('rate_sell', 18, 6);
            $table->string('source');
            $table->timestamp('fetched_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('spread_applied')->nullable();
            $table->timestamp('effective_date')->nullable();
            $table->index(['currency_code', 'fetched_at'], 'exchange_rates_currency_code_fetched_at_index');
            $table->unique(['branch_id', 'currency_code'], 'exchange_rates_branch_currency_unique');
            $table->index(['currency_code', 'effective_date'], 'exchange_rates_currency_code_effective_date_index');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->text('connection');
            $table->text('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->unique('uuid', 'failed_jobs_uuid_unique');
        });

        Schema::create('high_risk_countries', function (Blueprint $table) {
            $table->string('country_code')->primary();
            $table->string('country_name');
            $table->enum('risk_level', ['High', 'Grey']);
            $table->string('source');
            $table->date('list_date');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('risk_level', 'high_risk_countries_risk_level_index');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->text('failed_job_ids');
            $table->text('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->text('payload');
            $table->integer('attempts');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
            $table->index('queue', 'jobs_queue_index');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->string('account_code');
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->index('account_code', 'journal_lines_account_code_index');
            $table->index('journal_entry_id', 'journal_lines_journal_entry_id_index');
            $table->index('branch_id', 'journal_lines_branch_id_index');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('account_code')->references('account_code')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash');
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id', 'used'], 'mfa_recovery_codes_user_id_used_index');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->integer('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('password');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'id'], 'password_histories_user_id_id_index');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('pep_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->string('approval_level')->default('head_office_senior_management');
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('customer_id', 'pep_approval_requests_customer_id_index');
            $table->index('status', 'pep_approval_requests_status_index');
            $table->index('approval_level', 'pep_approval_requests_approval_level_index');
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->integer('tokenable_id');
            $table->text('name');
            $table->string('token');
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->unique('token', 'personal_access_tokens_token_unique');
            $table->index('expires_at', 'personal_access_tokens_expires_at_index');
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->string('cron_expression');
            $table->text('parameters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->text('notification_recipients')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['is_active', 'next_run_at'], 'report_schedules_is_active_next_run_at_index');
            $table->index('created_by', 'idx_98bb62ee4417');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('report_type');
            $table->text('parameters')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->integer('row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->integer('downloaded_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['report_type', 'status'], 'report_runs_report_type_status_index');
            $table->index('created_at', 'report_runs_created_at_index');
            $table->index(['generated_by', 'schedule_id'], 'idx_e02787454c0e');
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('schedule_id')->references('id')->on('report_schedules')->nullOnDelete();
        });

        Schema::create('reports_generated', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('generated_by');
            $table->timestamp('generated_at')->useCurrent();
            $table->string('file_path')->nullable();
            $table->string('file_format');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->enum('status', ['Generated', 'Submitted', 'Pending', 'Failed'])->default('Generated');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();
            $table->index('generated_by', 'reports_generated_generated_by_index');
            $table->index(['report_type', 'period_start', 'period_end'], 'reports_generated_report_type_period_start_period_end_index');
            $table->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('generated_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('revaluation_entries', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code');
            $table->string('till_id')->default('MAIN');
            $table->decimal('old_rate', 18, 6);
            $table->decimal('new_rate', 18, 6);
            $table->decimal('position_amount', 18, 4);
            $table->decimal('gain_loss_amount', 18, 4);
            $table->date('revaluation_date');
            $table->unsignedBigInteger('posted_by');
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['currency_code', 'revaluation_date'], 'revaluation_entries_currency_code_revaluation_date_index');
            $table->index('posted_at', 'revaluation_entries_posted_at_index');
            $table->index('posted_by', 'idx_03974fd3fb70');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('posted_by')->references('id')->on('users');
        });

        Schema::create('risk_score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->date('snapshot_date');
            $table->integer('overall_score')->default(0);
            $table->integer('velocity_score')->default(0);
            $table->integer('structuring_score')->default(0);
            $table->integer('geographic_score')->default(0);
            $table->integer('amount_score')->default(0);
            $table->string('trend')->default('stable');
            $table->text('factors')->nullable();
            $table->date('next_screening_date')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('previous_score')->nullable();
            $table->string('previous_rating')->nullable();
            $table->string('overall_rating_label')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['customer_id', 'snapshot_date'], 'risk_score_snapshots_customer_id_snapshot_date_index');
            $table->index('next_screening_date', 'risk_score_snapshots_next_screening_date_index');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('sanction_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('list_type');
            $table->string('source_file')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->boolean('is_active')->default(true);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('source_url')->nullable();
            $table->enum('source_format', ['XML', 'CSV', 'JSON'])->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->enum('update_status', ['success', 'failed', 'pending', 'never_run'])->default('never_run');
            $table->text('last_error_message')->nullable();
            $table->integer('entry_count')->default(0);
            $table->string('last_checksum')->nullable();
            $table->string('last_dataset_version')->nullable();
            $table->unsignedBigInteger('auto_updated_by')->nullable();
            $table->string('slug');
            $table->timestamp('deleted_at')->nullable();
            $table->index('is_active', 'sanction_lists_is_active_index');
            $table->index('list_type', 'sanction_lists_list_type_index');
            $table->unique('slug', 'sanction_lists_slug_unique');
            $table->index('uploaded_by', 'idx_7de69fde7ca3');
            $table->foreign('auto_updated_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('sanction_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->string('entity_name');
            $table->enum('entity_type', ['Individual', 'Organization', 'Vessel', 'Aircraft'])->default('Individual');
            $table->text('aliases')->nullable();
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->mediumText('details')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('normalized_name')->nullable();
            $table->string('soundex_code')->nullable();
            $table->string('metaphone_code')->nullable();
            $table->string('status')->default('active');
            $table->string('reference_number')->nullable();
            $table->date('listing_date')->nullable();
            $table->string('list_source')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index('list_id', 'sanction_entries_list_id_index');
            $table->index('entity_name', 'sanction_entries_entity_name_index');
            $table->index('normalized_name', 'sanction_entries_normalized_name_index');
            $table->index('soundex_code', 'sanction_entries_soundex_code_index');
            $table->index('metaphone_code', 'sanction_entries_metaphone_code_index');
            $table->index('status', 'sanction_entries_status_index');
            $table->foreign('list_id')->references('id')->on('sanction_lists')->restrictOnDelete();
        });

        Schema::create('sanction_import_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->timestamp('imported_at');
            $table->integer('records_added')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->enum('status', ['success', 'partial', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['scheduled', 'manual'])->default('scheduled');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['list_id', 'imported_at'], 'sanction_import_logs_list_id_imported_at_index');
            $table->index('user_id', 'idx_a1a62cf8a7c7');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('list_id')->references('id')->on('sanction_lists')->cascadeOnDelete();
        });

        Schema::create('sanctions_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('analysis_type');
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->timestamp('analyzed_at')->useCurrent();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('customer_id', 'sanctions_analyses_customer_id_index');
            $table->index('analysis_type', 'sanctions_analyses_analysis_type_index');
            $table->index('analyzed_at', 'sanctions_analyses_analyzed_at_index');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::create('screening_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('screened_name');
            $table->unsignedBigInteger('sanction_entry_id')->nullable();
            $table->string('match_type');
            $table->decimal('match_score', 5, 2);
            $table->string('action_taken');
            $table->string('result');
            $table->text('matched_fields')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('disposition')->nullable();
            $table->text('disposition_reason')->nullable();
            $table->unsignedBigInteger('dispositioned_by')->nullable();
            $table->timestamp('dispositioned_at')->nullable();
            $table->string('source')->default('sanctions');
            $table->unsignedBigInteger('adverse_media_entry_id')->nullable();
            $table->index(['sanction_entry_id', 'transaction_id', 'customer_id'], 'idx_cefb37a5e10e');
            $table->index(['disposition', 'result'], 'screening_results_disposition_result_index');
            $table->index(['result', 'created_at'], 'screening_results_result_created_at_index');
            $table->index('source', 'screening_results_source_index');
            $table->foreign('adverse_media_entry_id')->references('id')->on('adverse_media_entries')->nullOnDelete();
            $table->foreign('sanction_entry_id')->references('id')->on('sanction_entries')->restrictOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('dispositioned_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('setup_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction_id');
            $table->string('currency_code');
            $table->string('till_id');
            $table->decimal('amount_foreign', 18, 4);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->integer('created_by');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('transaction_id', 'stock_reservations_transaction_id_index');
            $table->index(['currency_code', 'till_id', 'status'], 'stock_reservations_currency_code_till_id_status_index');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number');
            $table->enum('type', ['Standard', 'Emergency', 'Scheduled', 'Return']);
            $table->enum('status', ['Requested', 'BranchManagerApproved', 'HqApproved', 'InTransit', 'PartiallyReceived', 'Received', 'Completed', 'Cancelled', 'Rejected'])->default('Requested');
            $table->string('source_branch_name')->nullable();
            $table->string('destination_branch_name')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('branch_manager_approved_by')->nullable();
            $table->timestamp('branch_manager_approved_at')->nullable();
            $table->unsignedBigInteger('hq_approved_by')->nullable();
            $table->timestamp('hq_approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('total_value_myr', 18, 4)->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['status', 'type'], 'stock_transfers_status_type_index');
            $table->index('requested_by', 'stock_transfers_requested_by_index');
            $table->unique('transfer_number', 'stock_transfers_transfer_number_unique');
            $table->index(['hq_approved_by', 'branch_manager_approved_by'], 'idx_6563f6a5a0e1');
            $table->foreign('hq_approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('branch_manager_approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_transfer_id');
            $table->string('currency_code');
            $table->decimal('quantity', 18, 4);
            $table->decimal('rate', 18, 6);
            $table->decimal('value_myr', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_in_transit', 18, 4)->default(0);
            $table->text('variance_notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('currency_code', 'stock_transfer_items_currency_code_index');
            $table->index('stock_transfer_id', 'idx_d7a732f0665b');
            $table->foreign('stock_transfer_id')->references('id')->on('stock_transfers')->cascadeOnDelete();
        });

        Schema::create('str_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('trigger_amount', 18, 4)->default(0);
            $table->text('trigger_reason');
            $table->string('status')->default('Draft');
            $table->string('bnm_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index('status', 'str_reports_status_idx');
            $table->index('customer_id', 'str_reports_customer_id_idx');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('case_id')->references('id')->on('compliance_cases')->nullOnDelete();
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['info', 'warning', 'critical'])->default('info');
            $table->text('message');
            $table->string('source')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['level', 'created_at'], 'system_alerts_level_created_at_index');
            $table->index(['acknowledged_at', 'created_at'], 'system_alerts_acknowledged_at_created_at_index');
            $table->index(['source', 'created_at'], 'system_alerts_source_created_at_index');
            $table->index('level', 'system_alerts_level_index');
            $table->index('source', 'system_alerts_source_index');
            $table->index('acknowledged_by', 'idx_f5bca9df4e98');
            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('system_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_name');
            $table->enum('status', ['ok', 'warning', 'critical'])->default('ok');
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['check_name', 'checked_at'], 'system_health_checks_check_name_checked_at_index');
            $table->index(['status', 'checked_at'], 'system_health_checks_status_checked_at_index');
            $table->index('check_name', 'system_health_checks_check_name_index');
            $table->index('status', 'system_health_checks_status_index');
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->integer('entity_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->enum('severity', ['INFO', 'WARNING', 'ERROR', 'CRITICAL'])->default('INFO');
            $table->string('session_id')->nullable();
            $table->text('description')->nullable();
            $table->string('previous_hash')->nullable();
            $table->string('entry_hash')->nullable();
            $table->index(['user_id', 'action'], 'system_logs_user_id_action_index');
            $table->index(['entity_type', 'entity_id'], 'system_logs_entity_type_entity_id_index');
            $table->index('created_at', 'system_logs_created_at_index');
            $table->index('session_id', 'system_logs_session_id_index');
            $table->index('action', 'idx_system_logs_action');
            $table->index('severity', 'idx_system_logs_severity');
            $table->index('entity_type', 'idx_system_logs_entity_type');
            $table->index(['user_id', 'created_at'], 'idx_system_logs_user_date');
            $table->index(['action', 'created_at'], 'idx_system_logs_action_date');
            $table->index(['severity', 'created_at'], 'idx_system_logs_severity_date');
            $table->index('previous_hash', 'system_logs_previous_hash_index');
            $table->index('user_id', 'system_logs_user_id_index');
            $table->index('entity_type', 'system_logs_entity_type_index');
            $table->index('ip_address', 'system_logs_ip_address_index');
            $table->index(['user_id', 'action', 'created_at'], 'system_logs_user_action_created_idx');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->string('run_id');
            $table->string('test_suite')->default('full');
            $table->integer('total_tests')->default(0);
            $table->integer('passed')->default(0);
            $table->integer('failed')->default(0);
            $table->integer('skipped')->default(0);
            $table->integer('assertions')->default(0);
            $table->decimal('duration', 8, 2);
            $table->enum('status', ['passed', 'failed', 'error', 'running'])->default('running');
            $table->text('output')->nullable();
            $table->text('failures')->nullable();
            $table->text('errors')->nullable();
            $table->string('git_branch')->nullable();
            $table->string('git_commit')->nullable();
            $table->string('executed_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('status', 'idx_test_results_status');
            $table->index('test_suite', 'idx_test_results_suite');
            $table->index('created_at', 'idx_test_results_created');
            $table->index(['status', 'created_at'], 'idx_test_results_status_created');
            $table->unique('run_id', 'test_results_run_id_unique');
        });

        Schema::create('threshold_audits', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('key');
            $table->string('old_value');
            $table->string('new_value');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['category', 'key'], 'threshold_audits_category_key_index');
            $table->index('changed_at', 'threshold_audits_changed_at_index');
            $table->index('changed_by', 'idx_4690221abca6');
            $table->foreign('changed_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('till_balances', function (Blueprint $table) {
            $table->id();
            $table->string('till_id');
            $table->string('currency_code');
            $table->decimal('opening_balance', 18, 4);
            $table->decimal('closing_balance', 18, 4)->nullable();
            $table->decimal('variance', 18, 4)->nullable();
            $table->date('date');
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('transaction_total', 18, 4)->default(0);
            $table->decimal('foreign_total', 18, 4)->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('teller_allocation_id')->nullable();
            $table->decimal('buy_total_foreign', 18, 4)->default(0);
            $table->decimal('sell_total_foreign', 18, 4)->default(0);
            $table->index('branch_id', 'till_balances_branch_id_index');
            $table->index('closed_at', 'till_balances_closed_at_index');
            $table->index(['currency_code', 'date'], 'till_balances_currency_date_idx');
            $table->index('date', 'till_balances_date_index');
            $table->index(['closed_by', 'opened_by', 'teller_allocation_id'], 'idx_8d2f3d6ddc8f');
            $table->foreign('teller_allocation_id')->references('id')->on('teller_allocations')->nullOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users');
            $table->foreign('opened_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('transaction_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->integer('user_id');
            $table->integer('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'expired'])->default('pending');
            $table->string('confirmation_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['transaction_id', 'status'], 'transaction_confirmations_transaction_id_status_index');
            $table->index('confirmation_token', 'transaction_confirmations_confirmation_token_index');
            $table->index('expires_at', 'transaction_confirmations_expires_at_index');
            $table->unique('confirmation_token', 'transaction_confirmations_confirmation_token_unique');
            $table->unique('transaction_id', 'transaction_confirmations_transaction_id_unique');
            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
        });

        Schema::create('transaction_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->string('error_type');
            $table->text('error_message');
            $table->text('error_context')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('transaction_id', 'transaction_errors_transaction_id_index');
            $table->index('error_type', 'transaction_errors_error_type_index');
            $table->index('retry_count', 'transaction_errors_retry_count_index');
            $table->index('resolved_at', 'transaction_errors_resolved_at_index');
            $table->index('resolved_by', 'idx_a2226d60fc83');
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });

        Schema::create('transaction_imports', function (Blueprint $table) {
            $table->id();
            $table->integer('imported_by');
            $table->text('filename');
            $table->text('original_filename');
            $table->integer('total_rows');
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->text('error_details')->nullable();
            $table->text('status');
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('file_hash')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('processed_rows')->default(0);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('notification_type');
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->string('webhook_url')->nullable();
            $table->text('custom_settings')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'notification_type'], 'unique_user_notification_type');
            $table->index('notification_type', 'idx_notification_type');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

    }
}
