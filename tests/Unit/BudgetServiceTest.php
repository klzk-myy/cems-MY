<?php

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BudgetService;
use App\Services\System\MathService;
use Database\Seeders\EnhancedChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BudgetService $budgetService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user (required for Budget.created_by FK)
        $this->adminUser = User::factory()->create(['role' => UserRole::Admin]);

        $this->budgetService = new BudgetService(
            app(AccountingService::class),
            app(MathService::class)
        );

        // Seed chart of accounts
        $this->seed(EnhancedChartOfAccountsSeeder::class);
    }

    #[Test]
    public function get_budget_report_returns_correct_structure(): void
    {
        $periodCode = now()->format('Y-m');

        // Create a budget for testing
        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        Budget::factory()->create([
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

    #[Test]
    public function get_budget_report_returns_items_with_correct_keys(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        Budget::factory()->create([
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

    #[Test]
    public function get_budget_report_calculates_variance_correctly(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        Budget::factory()->create([
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

    #[Test]
    public function get_budget_report_calculates_totals_correctly(): void
    {
        $periodCode = now()->format('Y-m');

        $expenseAccount = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $revenueAccount = ChartOfAccount::where('account_type', AccountType::Revenue)->first();
        $this->assertNotNull($expenseAccount, 'EnhancedChartOfAccountsSeeder should create an expense account');
        $this->assertNotNull($revenueAccount, 'EnhancedChartOfAccountsSeeder should create a revenue account');

        Budget::factory()->create([
            'account_code' => $expenseAccount->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        Budget::factory()->create([
            'account_code' => $revenueAccount->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '10000.00',
            'actual_amount' => '8000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertEquals('15000.0000', $report['total_budget']);
        $this->assertEquals('11000.0000', $report['total_actual']);
    }

    #[Test]
    public function get_accounts_without_budget_returns_expense_accounts(): void
    {
        $periodCode = now()->format('Y-m');

        $accountsWithoutBudget = $this->budgetService->getAccountsWithoutBudget($periodCode);

        $this->assertInstanceOf(Collection::class, $accountsWithoutBudget);
    }

    #[Test]
    public function budget_report_empty_for_no_budgets(): void
    {
        $periodCode = '2099-01'; // Future period with no budgets

        $report = $this->budgetService->getBudgetReport($periodCode);

        $this->assertEmpty($report['items']);
        $this->assertEquals('0', $report['total_budget']);
        $this->assertEquals('0', $report['total_actual']);
    }

    #[Test]
    public function budget_model_calculates_variance(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        $budget = Budget::factory()->create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00',
            'created_by' => $this->adminUser->id,
        ]);

        $variance = $budget->getVariance();
        $this->assertEquals(2000.00, $variance);
    }

    #[Test]
    public function budget_model_detects_over_budget(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        $budget = Budget::factory()->create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '6000.00', // Over budget
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertTrue($budget->isOverBudget());
    }

    #[Test]
    public function budget_model_detects_under_budget(): void
    {
        $periodCode = now()->format('Y-m');

        $account = ChartOfAccount::where('account_type', AccountType::Expense)->first();
        $this->assertNotNull($account, 'EnhancedChartOfAccountsSeeder should create an expense account');

        $budget = Budget::factory()->create([
            'account_code' => $account->account_code,
            'period_code' => $periodCode,
            'budget_amount' => '5000.00',
            'actual_amount' => '3000.00', // Under budget
            'created_by' => $this->adminUser->id,
        ]);

        $this->assertFalse($budget->isOverBudget());
    }

    #[Test]
    public function update_actuals_calculates_activity_for_all_budgets(): void
    {
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2099-02',
            'start_date' => '2099-02-01',
            'end_date' => '2099-02-28',
        ]);

        $account = ChartOfAccount::where('account_type', 'Expense')->first()
            ?? ChartOfAccount::factory()->create(['account_type' => 'Expense']);

        Budget::factory()->create([
            'account_code' => $account->account_code,
            'period_code' => $period->period_code,
            'created_by' => $this->adminUser->id,
        ]);

        \DB::enableQueryLog();
        $this->budgetService->updateActuals($period->period_code);
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $queries, "Expected <= 5 queries for batched updateActuals, got {$queries}");
    }
}
