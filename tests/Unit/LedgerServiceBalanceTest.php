<?php

namespace Tests\Unit;

use App\Models\ChartOfAccount;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerServiceBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create chart of accounts (Asset, Liability, Equity, Revenue, Expense types)
        $accountTypes = [
            ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '2000', 'account_name' => 'Payables', 'account_type' => 'Liability', 'is_active' => true],
            ['account_code' => '3000', 'account_name' => 'Capital', 'account_type' => 'Equity', 'is_active' => true],
            ['account_code' => '4000', 'account_name' => 'Sales', 'account_type' => 'Revenue', 'is_active' => true],
            ['account_code' => '5000', 'account_name' => 'Rent Expense', 'account_type' => 'Expense', 'is_active' => true],
        ];
        foreach ($accountTypes as $type) {
            ChartOfAccount::updateOrCreate(['account_code' => $type['account_code']], $type);
        }
    }

    /** @test */
    public function get_trial_balance_uses_efficient_queries()
    {
        $ledgerService = $this->app->make(LedgerService::class);

        DB::enableQueryLog();

        $result = $ledgerService->getTrialBalance(now()->toDateString());

        $queries = DB::getQueryLog();

        // Should use 2-3 queries, not 80-150
        $this->assertLessThan(
            5,
            count($queries),
            sprintf('Expected < 5 queries but got %d', count($queries))
        );

        // Verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('accounts', $result);
        $this->assertArrayHasKey('total_debits', $result);
        $this->assertArrayHasKey('total_credits', $result);
        $this->assertArrayHasKey('is_balanced', $result);
        $this->assertArrayHasKey('as_of_date', $result);

        // Verify we have accounts (some may be from default data)
        $this->assertNotEmpty($result['accounts']);
    }
}
