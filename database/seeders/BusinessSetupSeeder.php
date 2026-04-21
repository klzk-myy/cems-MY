<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BusinessSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('CEMS-MY Business Setup Seeder');
        $this->command->info('========================================');
        $this->command->info('');

        $this->command->info('Phase 1: Core Infrastructure');
        $this->call(UserSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(EnhancedChartOfAccountsSeeder::class);

        $this->command->info('');
        $this->command->info('Phase 2: Organizational Structure');
        $this->call(BranchSeeder::class);
        $this->call(CounterSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(CostCenterSeeder::class);

        $this->command->info('');
        $this->command->info('Phase 3: Accounting Framework');
        $this->call(FiscalYearSeeder::class);
        $this->call(AccountingPeriodSeeder::class);
        $this->call(BudgetSeeder::class);

        $this->command->info('');
        $this->command->info('Phase 4: Business Operations Data');
        $this->call(ExchangeRateSeeder::class);
        $this->call(BranchPoolSeeder::class);

        $this->command->info('');
        $this->command->info('Phase 5: Opening Balances (Optional)');
        $this->call(OpeningBalanceSeeder::class);

        $this->command->info('');
        $this->command->info('Phase 6: Compliance & Risk');
        $this->call(HighRiskCountrySeeder::class);
        $this->call(SanctionListSeeder::class);
        $this->call(AmlRuleSeeder::class);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Business Setup Complete!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('Next Steps:');
        $this->command->info('1. Login with: admin@cems.my / Admin@123456');
        $this->command->info('2. Verify exchange rates at /exchange-rates');
        $this->command->info('3. Open counter at /counters with opening float');
        $this->command->info('4. Start processing transactions');
        $this->command->info('');
    }
}
