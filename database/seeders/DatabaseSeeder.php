<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CurrencySeeder::class,
            ChartOfAccountsSeeder::class,
            EnhancedChartOfAccountsSeeder::class,
            AccountingPeriodSeeder::class,
            FiscalYearSeeder::class,
            BudgetSeeder::class,
            HighRiskCountrySeeder::class,
            SanctionListSeeder::class,
            AmlRuleSeeder::class,
            DepartmentSeeder::class,
            CostCenterSeeder::class,
            BranchSeeder::class,
            CounterSeeder::class,
            ExchangeRateSeeder::class,
            BranchPoolSeeder::class,
            TellerAllocationSeeder::class,
            OpeningBalanceSeeder::class,
        ]);
    }
}
