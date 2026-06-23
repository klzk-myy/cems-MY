<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BusinessSetup extends Command
{
    protected $signature = 'business:setup 
                            {--fresh : Run fresh migrations before setup}
                            {--seed-only : Only run seeders without migrations}
                            {--with-demo : Include demo data}';

    protected $description = 'Complete business setup for CEMS-MY - initializes all required components for operations';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║         CEMS-MY Business Setup Wizard                    ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->info('');

        if ($this->option('fresh')) {
            if (! $this->confirm('This will DROP all existing data. Are you sure?', false)) {
                $this->warn('Setup cancelled.');

                return 1;
            }

            $this->info('Running fresh migrations...');
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->info('Migrations complete.');
        }

        if (! $this->option('seed-only')) {
            $this->info('Running database migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $this->info('Migrations complete.');
        }

        $this->info('');
        $this->info('Phase 1: Core Infrastructure');
        $this->info('─────────────────────────────');
        Artisan::call('db:seed', ['--class' => 'UserSeeder', '--force' => true]);
        $this->info('Users created');

        Artisan::call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
        $this->info('Currencies created');

        Artisan::call('db:seed', ['--class' => 'ChartOfAccountsSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'EnhancedChartOfAccountsSeeder', '--force' => true]);
        $this->info('Chart of accounts created');

        $this->info('');
        $this->info('Phase 2: Organizational Structure');
        $this->info('──────────────────────────────────');
        Artisan::call('db:seed', ['--class' => 'BranchSeeder', '--force' => true]);
        $this->info('Branches created');

        Artisan::call('db:seed', ['--class' => 'CounterSeeder', '--force' => true]);
        $this->info('Counters created');

        $this->info('');
        $this->info('Phase 3: Accounting Framework');
        $this->info('──────────────────────────────');
        Artisan::call('db:seed', ['--class' => 'FiscalYearSeeder', '--force' => true]);
        $this->info('Fiscal year created');

        Artisan::call('db:seed', ['--class' => 'AccountingPeriodSeeder', '--force' => true]);
        $this->info('Accounting periods created');

        $this->info('');
        $this->info('Phase 4: Exchange Rates & Stock');
        $this->info('────────────────────────────────');

        if ($this->confirm('Do you want to use default exchange rates?', true)) {
            Artisan::call('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true]);
            $this->info('Default exchange rates set');
        } else {
            $this->warn('Exchange rates not set. You must manually add rates before trading.');
        }

        if ($this->confirm('Do you want to initialize branch currency pools with default amounts?', true)) {
            Artisan::call('db:seed', ['--class' => 'BranchPoolSeeder', '--force' => true]);
            $this->info('Branch currency pools initialized');
        } else {
            $this->warn('Branch pools not initialized. You must manually add stock before trading.');
        }

        if ($this->confirm('Do you want to pre-allocate currency to tellers?', true)) {
            Artisan::call('db:seed', ['--class' => 'TellerAllocationSeeder', '--force' => true]);
            $this->info('Teller allocations created');
        }

        $this->info('');
        $this->info('Phase 5: Opening Balances');
        $this->info('──────────────────────────');

        if ($this->confirm('Do you want to create opening balance journal entries?', false)) {
            Artisan::call('db:seed', ['--class' => 'OpeningBalanceSeeder', '--force' => true]);
            $this->info('Opening balances created');
        } else {
            $this->info('Skipping opening balances');
        }

        $this->info('');
        $this->info('Phase 6: Compliance & Risk');
        $this->info('───────────────────────────');
        Artisan::call('db:seed', ['--class' => 'HighRiskCountrySeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'SanctionListSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'AmlRuleSeeder', '--force' => true]);
        $this->info('Compliance data loaded');

        if ($this->option('with-demo')) {
            $this->info('');
            $this->info('Demo Data');
            $this->info('─────────');
            $this->warn('Demo data option selected but not yet implemented');
        }

        $this->printSummary();

        return 0;
    }

    private function printSummary(): void
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║              Setup Complete!                             ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->info('');

        $userCount = User::count();
        $currencyCount = Currency::count();
        $branchCount = Branch::count();
        $rateCount = ExchangeRate::count();
        $poolCount = BranchPool::count();
        $allocationCount = TellerAllocation::count();

        $this->info('Summary:');
        $this->info("  Users:           {$userCount}");
        $this->info("  Currencies:      {$currencyCount}");
        $this->info("  Branches:        {$branchCount}");
        $this->info("  Exchange Rates:  {$rateCount}");
        $this->info("  Branch Pools:    {$poolCount}");
        $this->info("  Teller Allocations: {$allocationCount}");
        $this->info('');

        $this->info('Login Credentials:');
        $this->info('  Admin:     admin@cems.my / Admin@123456');
        $this->info('  Teller:    teller1@cems.my / Teller@1234');
        $this->info('  Manager:   manager1@cems.my / Manager@1234');
        $this->info('  Compliance: compliance1@cems.my / Compliance@1234');
        $this->info('');

        $this->info('Next Steps:');
        $this->info('  1. Start the application: php artisan serve');
        $this->info('  2. Login at: http://localhost:8000/login');
        $this->info('  3. Verify exchange rates at /exchange-rates');
        $this->info('  4. Open a counter at /counters/{code}/open');
        $this->info('  5. Start processing transactions!');
        $this->info('');

        if ($rateCount === 0) {
            $this->warn('⚠️  Exchange rates are not set! Add rates before trading.');
        }

        if ($poolCount === 0) {
            $this->warn('⚠️  Branch currency pools are empty! Add stock before trading.');
        }
    }
}
