<?php

namespace App\Console\Commands;

use App\Enums\CounterSessionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTestDatabase extends Command
{
    protected $signature = 'db:reset-test
                            {--fresh : Drop and recreate all tables}
                            {--seed : Run seeders after reset}
                            {--demo : Create demo counter session and till balance}';

    protected $description = 'Reset database to clean state for manual testing';

    public function handle(): int
    {
        $this->info('Starting database reset...');

        if ($this->option('fresh')) {
            $this->warn('Dropping all tables and recreating...');
            $this->call('migrate:fresh', ['--seed' => $this->option('seed')]);
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
            'str_drafts',
            'str_reports',
            'ctos_reports',
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
            DB::table('counters')->update(['status' => 'active']);
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
            // Get currency
            $currency = DB::table('currencies')->where('code', $currencyCode)->first();
            if (! $currency) {
                continue;
            }

            // Create opening position for counter
            $openingAmount = match ($currencyCode) {
                'USD' => '50000.0000',
                'EUR' => '30000.0000',
                'GBP' => '20000.0000',
                'SGD' => '40000.0000',
                'THB' => '100000.0000',
                default => '10000.0000',
            };

            // Create till balance
            DB::table('till_balances')->insert([
                'till_id' => (string) $counter->id,
                'currency_code' => $currencyCode,
                'opening_balance' => $openingAmount,
                'transaction_total' => '0.0000',
                'foreign_total' => '0.0000',
                'date' => now()->toDateString(),
                'opened_by' => $teller->id,
            ]);

            // Create currency position
            DB::table('currency_positions')->insert([
                'currency_code' => $currencyCode,
                'till_id' => (string) $counter->id,
                'balance' => $openingAmount,
                'avg_cost_rate' => match ($currencyCode) {
                    'USD' => '4.7200',
                    'EUR' => '5.1200',
                    'GBP' => '5.9500',
                    'SGD' => '3.5000',
                    'THB' => '0.1350',
                    default => '1.0000',
                },
                'last_valuation_rate' => match ($currencyCode) {
                    'USD' => '4.7200',
                    'EUR' => '5.1200',
                    'GBP' => '5.9500',
                    'SGD' => '3.5000',
                    'THB' => '0.1350',
                    default => '1.0000',
                },
                'unrealized_pnl' => '0.0000',
            ]);

            $this->line("  - Created till balance for {$currencyCode}: {$openingAmount}");
        }

        $this->info('Demo session ready!');
        $this->line('');
        $this->info('Login credentials:');
        $this->line('  Teller: teller1 / Teller@1234');
        $this->line('  Manager: manager1 / Manager@1234');
        $this->line('  Admin: admin / Admin@123456');
    }
}
