<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BudgetService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BudgetService $budgetService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user (required for Budget.created_by FK)
        $this->adminUser = User::factory()->create(['role' => \App\Enums\UserRole::Admin]);

        $this->budgetService = new BudgetService(
            app(AccountingService::class),
            app(MathService::class)
        );

        // Seed chart of accounts
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    }

    public function test_get_budget_report_returns_correct_structure(): void
    {
        $periodCode = now()->format('Y-m');

        // Create a budget for testing
        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertArrayHasKey('period_code', $report);
        $this->assertArrayHasKey('items', $report);
        $this->assertArrayHasKey('total_budget', $report);
        $this->assertArrayHasKey('total_actual', $report);
        $this->assertArrayHasKey('total_variance', $report);
        $this->assertEquals($periodCode, $report['period_code']);
    }

    public function test_get_budget_report_returns_items_with_correct_keys(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertNotEmpty($report['items']);
        $item = $report['items'][0];
        $this->assertArrayHasKey('account_code', $item);
        $this->assertArrayHasKey('account_name', $item);
        $this->assertArrayHasKey('budget', $item);
        $this->assertArrayHasKey('actual', $item);
        $this->assertArrayHasKey('variance', $item);
    }

    public function test_get_budget_report_calculates_variance_correctly(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $report = $this->budgetService->getBudgetReport($periodCode);

        // Variance should be budget - actual = 5000 - 3000 = 2000
        $this->assertEquals('2000', $report['items'][0]['variance']);
    }

    public function test_get_budget_report_calculates_totals_correctly(): void
    {
        $periodCode = now()->format('Y-m');

        $expenseAccount = ChartOfAccount::where('account_type', 'Expense')->first();
        $revenueAccount = ChartOfAccount::where('account_type', 'Revenue')->first();

        if (! $expenseAccount || ! $revenueAccount) {
            $this->markTestSkipped('No expense or revenue account found');
        }

        Budget::create([
            'account_code' => $expenseAccount->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        Budget::create([
            'account_code' => $revenueAccount->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '10000.00',
            'actual_amount' => '8000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertEquals('15000.000000', $report['total_budget']);
        $this->assertEquals('11000.000000', $report['total_actual']);
    }

    public function test_get_accounts_without_budget_returns_expense_accounts(): void
    {
        $periodCode = now()->format('Y-m');

        $accountsWithoutBudget = $this->budgetService->getAccountsWithoutBudget($periodCode);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $accountsWithoutBudget);
    }

    public function test_budget_report_empty_for_no_budgets(): void
    {
        $periodCode = '2099-01'; // Future period with no budgets

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertEmpty($report['items']);
        $this->assertEquals('0', $report['total_budget']);
        $this->assertEquals('0', $report['total_actual']);
    }

    public function test_budget_model_calculates_variance(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        $budget = Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $variance = $budget->getVariance();
        $this->assertEquals(2000.00, $variance);
    }

    public function test_budget_model_detects_over_budget(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        $budget = Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '6000.00', // Over budget
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertTrue($budget->isOverBudget());
    }

    public function test_budget_model_detects_under_budget(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', 'Expense')->first();
        if (! $account) {
            $this->markTestSkipped('No expense account found');
        }

        $budget = Budget::create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00', // Under budget
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertFalse($budget->isOverBudget());
    }
}
