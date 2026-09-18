<?php

namespace App\Console\Commands;

use App\Enums\CounterSessionStatus;
use App\Enums\CounterStatus;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SchemaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetTestDatabase extends Command
{
    protected $signature = 'db:reset-test
                            {--fresh : Drop and recreate all tables}
                            {--seed : Run seeders after reset}
                            {--demo : Create demo counter session and till balance}';

    protected $description = 'Reset database to clean state for manual testing';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command is only available in local/testing environments.');

            return Command::FAILURE;
        }

        $this->info('Starting database reset...');

        if ($this->option('fresh')) {
            $this->warn('Dropping all tables and recreating...');

            // DatabaseSeeder leads with SchemaSeeder, so a --seed run needs
            // only one call: seeding it directly avoids rebuilding the whole
            // schema twice.
            if ($this->option('seed')) {
                $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
            } else {
                $this->call('db:seed', ['--class' => SchemaSeeder::class, '--force' => true]);
            }

            $this->info('Database refreshed with seeding.');
        } else {
            $this->truncateTestData();
        }

        if ($this->option('demo')) {
            $this->setupDemoSession();
        }

        $this->info('Database reset complete!');

        return Command::SUCCESS;
    }

    protected function truncateTestData(): void
    {
        $tables = [
            // Compliance tables
            'compliance_case_notes',
            'compliance_cases',
            'compliance_findings',
            'transaction_confirmations',
            'flagged_transactions',
            'edd_questionnaire_responses',
            'enhanced_diligence_records',
            'aml_alerts',
            'alerts',

            // Accounting tables (reversed entries last due to FK)
            'journal_lines',
            'journal_entries',
            'account_ledgers',
            'revaluation_entries',
            'budget_actuals',
            'bank_reconciliations',
            'bank_reconciliation_items',

            // Transaction tables
            'transactions',
            'transaction_errors',

            // Counter tables
            'counter_handovers',
            'counter_sessions',
            'till_balances',
            'currency_positions',
            'stock_transfers',
            'stock_transfer_items',

            // Customer tables
            'customer_documents',
            'customer_risk_history',

            // Notification tables
            'notifications',
            'user_notification_preferences',

            // Audit/logs
            'system_logs',
            'audit_logs',
            'data_breach_alerts',
            'backup_logs',
            'report_runs',

            // User sessions
            'sessions',
            'personal_access_tokens',
        ];

        $this->info('Truncating test data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  - Truncated: {$table}");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Reset counters table (only if column exists)
        if (Schema::hasTable('counters') && Schema::hasColumn('counters', 'status')) {
            DB::table('counters')->update(['status' => CounterStatus::Active->value]);
        }

        // Reset users password hash timestamps (only if column exists)
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'password_changed_at')) {
            DB::table('users')->update(['password_changed_at' => null]);
        }

        $this->info('All test data truncated.');
    }

    protected function setupDemoSession(): void
    {
        $this->info('Setting up demo counter session...');

        // Get teller user
        $teller = DB::table('users')->where('username', 'teller1')->first();
        if (! $teller) {
            $this->error('Teller user not found. Run seeders first.');

            return;
        }

        // Get counter
        $counter = DB::table('counters')->where('code', 'C01')->first();
        if (! $counter) {
            $this->error('Counter C01 not found.');

            return;
        }

        // Create open counter session
        $sessionId = DB::table('counter_sessions')->insertGetId([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'opened_by' => $teller->id,
            'status' => CounterSessionStatus::Open->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  - Created counter session #{$sessionId}");

        // Create till balances for major currencies
        $currencies = ['USD', 'EUR', 'GBP', 'SGD', 'THB'];
        foreach ($currencies as $currencyCode) {
            $currency = DB::table('currencies')->where('code', $currencyCode)->first();
            if (! $currency) {
                continue;
            }

            $openingAmount = config("cems.demo.opening_balances.{$currencyCode}", '10000.0000');
            $exchangeRate = DB::table('exchange_rates')
                ->where('currency_code', $currencyCode)
                ->orderBy('created_at', 'desc')
                ->value('rate');
            $rate = $exchangeRate ? number_format((float) $exchangeRate, 4, '.', '') : '1.0000';

            DB::table('till_balances')->insert([
                'till_id' => (string) $counter->code,
                'currency_code' => $currencyCode,
                'opening_balance' => $openingAmount,
                'transaction_total' => '0.0000',
                'foreign_total' => '0.0000',
                'date' => now()->toDateString(),
                'opened_by' => $teller->id,
            ]);

            DB::table('currency_positions')->insert([
                'currency_code' => $currencyCode,
                'till_id' => (string) $counter->code,
                'balance' => $openingAmount,
                'avg_cost_rate' => $rate,
                'last_valuation_rate' => $rate,
                'unrealized_pnl' => '0.0000',
            ]);

            $this->line("  - Created till balance for {$currencyCode}: {$openingAmount}");
        }

        $this->info('Demo session ready!');
        $this->line('');
        $this->warn('Temporary demo credentials (store securely):');
        // Str::password produces mixed-case + digit + symbol secrets that
        // satisfy the complexity policy (Str::random is alphanumeric only).
        $tellerPass = Str::password(12);
        $managerPass = Str::password(12);
        $adminPass = Str::password(16);

        // The credential column is password_hash (there is no password
        // column); stamp changed_at so the demo credentials are not already
        // expired under the rotation policy.
        DB::table('users')->where('username', 'teller1')->update(['password_hash' => Hash::make($tellerPass), 'password_changed_at' => now()]);
        DB::table('users')->where('username', 'manager1')->update(['password_hash' => Hash::make($managerPass), 'password_changed_at' => now()]);
        DB::table('users')->where('username', 'admin')->update(['password_hash' => Hash::make($adminPass), 'password_changed_at' => now()]);

        $this->line("  Teller:  teller1 / {$tellerPass}");
        $this->line("  Manager: manager1 / {$managerPass}");
        $this->line("  Admin:   admin / {$adminPass}");
    }
}
