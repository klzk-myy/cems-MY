<?php

namespace Tests\Unit\Services\Reporting;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Services\Reporting\FinancialRatioService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialRatioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected FinancialRatioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->service = new FinancialRatioService($this->mathService);
    }

    public function test_liquidity_ratios_query_count_is_optimized(): void
    {
        $asOfDate = '2024-01-31';

        // Create Asset accounts (1300, 1400, 1500)
        ChartOfAccount::factory()->createMany([
            ['account_code' => '1300', 'account_type' => 'Asset', 'account_name' => 'Prepaid', 'account_class' => 'Prepaid'],
            ['account_code' => '1400', 'account_type' => 'Asset', 'account_name' => 'Deposit', 'account_class' => 'Asset'],
            ['account_code' => '1500', 'account_type' => 'Asset', 'account_name' => 'Receivable', 'account_class' => 'Receivable'],
        ]);

        // Create Liability accounts (2100, 2200, 2300)
        ChartOfAccount::factory()->createMany([
            ['account_code' => '2100', 'account_type' => 'Liability', 'account_name' => 'Payable', 'account_class' => 'Payable'],
            ['account_code' => '2200', 'account_type' => 'Liability', 'account_name' => 'Accrued', 'account_class' => 'Accrued'],
            ['account_code' => '2300', 'account_type' => 'Liability', 'account_name' => 'Deferred', 'account_class' => 'Liability'],
        ]);

        // Create ledger entries for balance sheet accounts (as of $asOfDate)
        $assets = ['1300', '1400', '1500'];
        foreach ($assets as $code) {
            AccountLedger::factory()->create([
                'account_code' => $code,
                'entry_date' => $asOfDate,
                'running_balance' => (string) rand(1000, 10000),
            ]);
        }

        $liabilities = ['2100', '2200', '2300'];
        foreach ($liabilities as $code) {
            AccountLedger::factory()->create([
                'account_code' => $code,
                'entry_date' => $asOfDate,
                'running_balance' => (string) rand(1000, 10000),
            ]);
        }

        DB::enableQueryLog();

        $ratios = $this->service->getLiquidityRatios($asOfDate);

        $queryLog = DB::getQueryLog();
        $queries = count($queryLog);

        // Always write to file for analysis
        file_put_contents('/tmp/liquidity_queries.json', json_encode($queryLog, JSON_PRETTY_PRINT));
        file_put_contents('/tmp/liquidity_query_count.txt', (string) $queries);

        // With aggregates: each balance method uses ~2-3 queries. Liquidity calls 4 such methods.
        // Expected total should be <= 15.
        $this->assertLessThanOrEqual(15, $queries, "Expected <= 15 queries for getLiquidityRatios(), got {$queries}");

        $this->assertArrayHasKey('current_ratio', $ratios);
        $this->assertArrayHasKey('quick_ratio', $ratios);
        $this->assertArrayHasKey('cash_ratio', $ratios);
        $this->assertArrayHasKey('current_assets', $ratios);
        $this->assertArrayHasKey('current_liabilities', $ratios);
        $this->assertArrayHasKey('inventory', $ratios);
        $this->assertArrayHasKey('cash', $ratios);
    }
}
