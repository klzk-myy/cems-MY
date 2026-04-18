<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;

/**
 * Budget Service
 *
 * Manages budget creation, updates, and reporting for accounting periods.
 * Provides functionality for tracking budget vs actual amounts and identifying
 * accounts without budgets.
 */
class BudgetService
{
    /**
     * Accounting service for calculating account activity.
     */
    protected AccountingService $accountingService;

    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Create a new BudgetService instance.
     *
     * @param  AccountingService  $accountingService  Service for account activity calculations
     * @param  MathService  $mathService  Service for high-precision math operations
     */
    public function __construct(AccountingService $accountingService, MathService $mathService)
    {
        $this->accountingService = $accountingService;
        $this->mathService = $mathService;
    }

    /**
     * Create or update budget for an account in a period.
     *
     * @param  string  $accountCode  Unique identifier for the chart of account
     * @param  string  $periodCode  Accounting period identifier (e.g., "2024-01")
     * @param  string  $amount  Budget amount as string for precision
     * @param  int  $userId  ID of the user creating/updating the budget
     * @param  string|null  $notes  Optional notes or comments about the budget
     * @return Budget The created or updated budget model
     */
    public function setBudget(string $accountCode, string $periodCode, string $amount, int $userId, ?string $notes = null): Budget
    {
        return Budget::updateOrCreate(
            [
                'account_code' => $accountCode,
                'period_code' => $periodCode,
            ],
            [
                'budget_amount' => $amount,
                'created_by' => $userId,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Update actual amounts for all budgets in a period.
     * Calculates actuals based on activity within the period date range.
     *
     * @param  string  $periodCode  Accounting period identifier (e.g., "2024-01")
     */
    public function updateActuals(string $periodCode): void
    {
        // Get the period to determine date range
        $period = AccountingPeriod::where('period_code', $periodCode)->first();

        if (! $period) {
            return;
        }

        $budgets = Budget::where('period_code', $periodCode)->get();
        foreach ($budgets as $budget) {
            // Get actual activity for the account within the period date range
            $actual = $this->accountingService->getAccountActivity(
                $budget->account_code,
                $period->start_date->toDateString(),
                $period->end_date->toDateString()
            );
            $budget->update(['actual_amount' => $actual]);
        }
    }

    /**
     * Get budget vs actual report for period.
     *
     * @param  string  $periodCode  Accounting period identifier (e.g., "2024-01")
     * @return array Budget report containing:
     *               - period_code: string, the period identifier
     *               - items: array of account budget details with keys:
     *               - account_code: string
     *               - account_name: string
     *               - budget: string
     *               - actual: string
     *               - variance: string
     *               - variance_pct: float|null
     *               - over_budget: bool
     *               - total_budget: string, sum of all budget amounts
     *               - total_actual: string, sum of all actual amounts
     *               - total_variance: string, difference between total budget and actual
     *               - over_budget_count: int, number of accounts exceeding budget
     */
    public function getBudgetReport(string $periodCode): array
    {
        $budgets = Budget::with('account')
            ->where('period_code', $periodCode)
            ->get();

        $totalBudget = '0';
        $totalActual = '0';
        $items = [];

        foreach ($budgets as $budget) {
            $variance = $budget->getVariance();
            $items[] = [
                'account_code' => $budget->account_code,
                'account_name' => $budget->account->account_name,
                'budget' => (string) $budget->budget_amount,
                'actual' => (string) $budget->actual_amount,
                'variance' => (string) $variance,
                'variance_pct' => $budget->getVariancePercentage(),
                'over_budget' => $budget->isOverBudget(),
            ];
            $totalBudget = $this->mathService->add($totalBudget, (string) $budget->budget_amount);
            $totalActual = $this->mathService->add($totalActual, (string) $budget->actual_amount);
        }

        return [
            'period_code' => $periodCode,
            'items' => $items,
            'total_budget' => $totalBudget,
            'total_actual' => $totalActual,
            'total_variance' => $this->mathService->subtract($totalBudget, $totalActual),
            'over_budget_count' => $budgets->filter(fn ($b) => $b->isOverBudget())->count(),
        ];
    }

    /**
     * Get accounts without budgets for period.
     *
     * Returns expense accounts that have not been assigned a budget
     * for the specified accounting period.
     *
     * @param  string  $periodCode  Accounting period identifier (e.g., "2024-01")
     * @return Collection Collection of ChartOfAccount models for active expense accounts without budgets
     */
    public function getAccountsWithoutBudget(string $periodCode): Collection
    {
        $budgetedAccounts = Budget::where('period_code', $periodCode)
            ->pluck('account_code');

        return ChartOfAccount::where('account_type', 'Expense')
            ->where('is_active', true)
            ->whereNotIn('account_code', $budgetedAccounts)
            ->get();
    }
}
