<?php

namespace App\Services\Accounting;

use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * @param  string  $budgetMyr  Budget amount (MYR) as string for precision
     * @param  int  $userId  ID of the user creating/updating the budget
     * @param  string|null  $notes  Optional notes or comments about the budget
     * @return Budget The created or updated budget model
     */
    public function setBudget(string $accountCode, string $periodCode, string $budgetMyr, int $userId, ?string $notes = null): Budget
    {
        return Budget::updateOrCreate(
            [
                'account_code' => $accountCode,
                'period_code' => $periodCode,
            ],
            [
                'budget_myr' => $budgetMyr,
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
        $period = AccountingPeriod::where('period_code', $periodCode)->first();

        if (! $period) {
            Log::warning("BudgetService::updateActuals: accounting period '{$periodCode}' not found");
            throw new AccountingPeriodException("Accounting period '{$periodCode}' not found");
        }

        $budgets = Budget::where('period_code', $periodCode)->get();

        if ($budgets->isEmpty()) {
            Log::info("BudgetService::updateActuals: no budgets found for period '{$periodCode}'");

            return;
        }

        $activity = $this->accountingService->getAccountsActivity(
            $budgets->pluck('account_code')->unique()->toArray(),
            $period->start_date->toDateString(),
            $period->end_date->toDateString()
        );

        DB::transaction(function () use ($budgets, $activity) {
            foreach ($budgets as $budget) {
                $budget->update(['actual_myr' => $activity[$budget->account_code] ?? '0']);
            }
        });
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

        // Compute actuals live from ledger activity — the stored actual_myr
        // is only refreshed by updateActuals(), which nothing calls, so the
        // report must not trust the column.
        $period = AccountingPeriod::where('period_code', $periodCode)->first();
        $liveActivity = $period && $budgets->isNotEmpty()
            ? $this->accountingService->getAccountsActivity(
                $budgets->pluck('account_code')->unique()->toArray(),
                $period->start_date->toDateString(),
                $period->end_date->toDateString()
            )
            : [];

        $totalBudget = '0';
        $totalActual = '0';
        $items = [];

        $overBudgetCount = 0;
        foreach ($budgets as $budget) {
            $actual = (string) ($liveActivity[$budget->account_code] ?? $budget->actual_myr);
            $variance = $this->mathService->subtract((string) $budget->budget_myr, $actual);
            $overBudget = $this->mathService->compare($variance, '0') < 0;
            $overBudgetCount += $overBudget ? 1 : 0;
            $items[] = [
                'id' => $budget->id,
                'account_code' => $budget->account_code,
                'account_name' => $budget->account->account_name,
                'budget' => (string) $budget->budget_myr,
                'actual' => $actual,
                'variance' => $variance,
                'variance_pct' => $this->mathService->compare((string) $budget->budget_myr, '0') > 0
                    ? (float) $this->mathService->multiply($this->mathService->divide($variance, (string) $budget->budget_myr), '100')
                    : null,
                'over_budget' => $overBudget,
            ];
            $totalBudget = $this->mathService->add($totalBudget, (string) $budget->budget_myr);
            $totalActual = $this->mathService->add($totalActual, $actual);
        }

        return [
            'period_code' => $periodCode,
            'items' => $items,
            'total_budget' => $totalBudget,
            'total_actual' => $totalActual,
            'total_variance' => $this->mathService->subtract($totalBudget, $totalActual),
            'over_budget_count' => $overBudgetCount,
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
