<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // DatabaseSeeder leads with SchemaSeeder, which drops every table.
        // Refuse to run against a populated database so the habitual
        // `php artisan db:seed` cannot wipe it. Fresh installs have no users,
        // so the guard only trips when data exists; a deliberate full rebuild
        // is `db:seed --class=SchemaSeeder` (or ResetTestDatabase --fresh).
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            throw new RuntimeException(
                'Database is not empty. DatabaseSeeder rebuilds the schema via SchemaSeeder and would drop all data. '
                .'Run the individual seeders instead, or use SchemaSeeder deliberately on an empty database.'
            );
        }

        $this->call([
            SchemaSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            CurrencySeeder::class,
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
