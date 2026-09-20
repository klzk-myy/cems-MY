<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Deliberate rebuild paths (db:reset-test --fresh --seed) opt in by
     * setting this flag; bare `db:seed` on a populated database is refused.
     */
    public static bool $allowPopulated = false;

    public function run(): void
    {
        // DatabaseSeeder leads with SchemaSeeder, which drops every table.
        // Refuse to run against a populated database so the habitual
        // `php artisan db:seed` cannot wipe it. Fresh installs have no users,
        // so the guard only trips when data exists; a deliberate full rebuild
        // is `db:reset-test --fresh` (SchemaSeeder itself carries the same
        // populated-database guard).
        if (! self::$allowPopulated
            && Schema::hasTable('users')
            && DB::table('users')->exists()
        ) {
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
            AccountMappingsSeeder::class,
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

        // The demo seed produces a working environment, not a first install —
        // mark setup complete so seeded users land in the app, not the wizard.
        // (The real setup wizard path — seedNow/ReferenceDataSeeder — must NOT
        // set this: an empty setup_state is what routes a fresh install there.)
        DB::table('setup_state')->updateOrInsert(
            ['id' => 1],
            ['setup_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
