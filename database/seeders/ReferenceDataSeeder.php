<?php

namespace Database\Seeders;

use App\Enums\AmlRuleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference rows that the historical migrations inserted on a fresh
 * install (base currencies, base chart of accounts, and the surviving
 * AML rule after the legacy-rule cleanup), plus the enum-backed chart
 * of accounts and default posting-map rows.
 *
 * Extracted from SchemaSeeder so that reseeding produces a truly clean
 * database — schema only, zero rows. Flows that need an install-ready
 * baseline (setup wizard, tests, deploy) run this seeder explicitly.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
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

        // Same default posting-map rows a real install gets — posting
        // services fall back to enum defaults without them, but the page and
        // tests expect the rows to exist.
        (new AccountMappingsSeeder)->run();
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
}
