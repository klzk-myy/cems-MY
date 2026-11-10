<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Services\System\MathService;

/**
 * Financial Ratio Service
 *
 * Calculates financial ratios for liquidity, profitability, leverage, and efficiency analysis.
 */
class FinancialRatioService
{
    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Create a new FinancialRatioService instance.
     */
    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
    }

    /**
     * Get liquidity ratios.
     *
     * Current Ratio = Current Assets / Current Liabilities
     * Quick Ratio = (Current Assets - Inventory) / Current Liabilities
     * Cash Ratio = Cash / Current Liabilities
     *
     * @param  string  $asOfDate  Date for balance calculation (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return array Liquidity ratios
     */
    public function getLiquidityRatios(string $asOfDate, ?int $branchId = null): array
    {
        $currentAssets = $this->getCurrentAssets($asOfDate, $branchId);
        $currentLiabilities = $this->getCurrentLiabilities($asOfDate, $branchId);
        $inventory = $this->getInventory($asOfDate, $branchId);
        $cash = $this->getCashBalance($asOfDate, $branchId);

        $currentRatio = $this->mathService->safeDivide($currentAssets, $currentLiabilities);
        $quickRatio = $this->mathService->safeDivide(
            $this->mathService->subtract($currentAssets, $inventory),
            $currentLiabilities
        );
        $cashRatio = $this->mathService->safeDivide($cash, $currentLiabilities);

        return [
            'current_ratio' => $currentRatio,
            'quick_ratio' => $quickRatio,
            'cash_ratio' => $cashRatio,
            'current_assets' => $currentAssets,
            'current_liabilities' => $currentLiabilities,
            'inventory' => $inventory,
            'cash' => $cash,
        ];
    }

    /**
     * Get profitability ratios.
     *
     * Gross Profit Margin = (Revenue - COGS) / Revenue
     * Net Profit Margin = Net Income / Revenue
     * ROE = Net Income / Equity
     * ROA = Net Income / Total Assets
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return array Profitability ratios
     */
    public function getProfitabilityRatios(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $revenue = $this->getTotalRevenue($fromDate, $toDate, $branchId);
        $cogs = $this->getTotalCOGS($fromDate, $toDate, $branchId);
        $netIncome = $this->getNetIncome($fromDate, $toDate, $branchId);
        $equity = $this->getTotalEquity($toDate, $branchId);
        $totalAssets = $this->getTotalAssets($toDate, $branchId);

        $grossProfit = $this->mathService->subtract($revenue, $cogs);
        $grossMargin = $this->mathService->safeDivide($grossProfit, $revenue);
        $netMargin = $this->mathService->safeDivide($netIncome, $revenue);
        $roe = $this->mathService->safeDivide($netIncome, $equity);
        $roa = $this->mathService->safeDivide($netIncome, $totalAssets);

        return [
            'gross_profit_margin' => $grossMargin,
            'net_profit_margin' => $netMargin,
            'roe' => $roe,
            'roa' => $roa,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'net_income' => $netIncome,
            'equity' => $equity,
            'total_assets' => $totalAssets,
        ];
    }

    /**
     * Get leverage ratios.
     *
     * Debt-to-Equity = Total Debt / Equity
     * Debt-to-Assets = Total Debt / Total Assets
     *
     * @param  string  $asOfDate  Date for balance calculation (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return array Leverage ratios
     */
    public function getLeverageRatios(string $asOfDate, ?int $branchId = null): array
    {
        $totalDebt = $this->getTotalLiabilities($asOfDate, $branchId);
        $equity = $this->getTotalEquity($asOfDate, $branchId);
        $totalAssets = $this->getTotalAssets($asOfDate, $branchId);

        $debtToEquity = $this->mathService->safeDivide($totalDebt, $equity);
        $debtToAssets = $this->mathService->safeDivide($totalDebt, $totalAssets);

        return [
            'debt_to_equity' => $debtToEquity,
            'debt_to_assets' => $debtToAssets,
            'total_debt' => $totalDebt,
            'equity' => $equity,
            'total_assets' => $totalAssets,
        ];
    }

    /**
     * Get efficiency ratios.
     *
     * Asset Turnover = Revenue / Total Assets
     * Inventory Turnover = COGS / Inventory
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return array Efficiency ratios
     */
    public function getEfficiencyRatios(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $revenue = $this->getTotalRevenue($fromDate, $toDate, $branchId);
        $cogs = $this->getTotalCOGS($fromDate, $toDate, $branchId);
        $totalAssets = $this->getTotalAssets($toDate, $branchId);
        $inventory = $this->getInventory($toDate, $branchId);

        $assetTurnover = $this->mathService->safeDivide($revenue, $totalAssets);
        $inventoryTurnover = $this->mathService->safeDivide($cogs, $inventory);

        return [
            'asset_turnover' => $assetTurnover,
            'inventory_turnover' => $inventoryTurnover,
            'revenue' => $revenue,
            'total_assets' => $totalAssets,
            'cogs' => $cogs,
            'inventory' => $inventory,
        ];
    }

    /**
     * Get current assets (Asset accounts in 1000-1999 range).
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCurrentAssets(string $asOfDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', AccountType::Asset->value)
            ->whereBetween('account_code', ['1000', '2199'])
            ->pluck('account_code')
            ->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get current liabilities (Liability accounts).
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCurrentLiabilities(string $asOfDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', AccountType::Liability->value)
            ->whereBetween('account_code', ['3000', '3199'])
            ->pluck('account_code')
            ->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get inventory balance.
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getInventory(string $asOfDate, ?int $branchId = null): string
    {
        $total = '0';
        // Inventory accounts: those explicitly classified as Inventory, or falling
        // in the 2000-2499 range when no explicit classification exists. Union the
        // two sets so a zero-balance inventory class can never double-count a range
        // account that is already included.
        $inventoryAccounts = ChartOfAccount::where('account_class', 'Inventory')
            ->orWhereBetween('account_code', ['2000', '2499'])
            ->pluck('account_code')
            ->unique()
            ->toArray();

        if (! empty($inventoryAccounts)) {
            $balances = $this->getLatestBalances($inventoryAccounts, $asOfDate, $branchId);
            foreach ($balances as $balance) {
                $total = $this->mathService->add($total, $balance);
            }
        }

        return $total;
    }

    /**
     * Get cash balance (cash + bank accounts).
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCashBalance(string $asOfDate, ?int $branchId = null): string
    {
        // Cash accounts (1000-1499 range)
        $accountCodes = ChartOfAccount::whereBetween('account_code', ['1000', '1499'])->pluck('account_code')->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get total revenue for a period.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalRevenue(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', 'Revenue')->pluck('account_code')->toArray();

        if (empty($accountCodes)) {
            return '0';
        }

        $query = AccountLedger::query()
            ->whereIn('account_code', $accountCodes)
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->selectRaw('SUM(credit - debit) as net');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        // value('net') is NULL when there are no ledger rows in the period;
        // passing '' into bcmath throws a ValueError, so coerce to '0'.
        return (string) ($query->value('net') ?? '0');
    }

    /**
     * Get total COGS for a period.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalCOGS(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::whereBetween('account_code', ['6000', '6499'])->pluck('account_code')->toArray();

        if (empty($accountCodes)) {
            return '0';
        }

        $query = AccountLedger::query()
            ->whereIn('account_code', $accountCodes)
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->selectRaw('SUM(debit - credit) as net');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (string) ($query->value('net') ?? '0');
    }

    /**
     * Get net income (Revenue - Expenses).
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getNetIncome(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $revenue = $this->getTotalRevenue($fromDate, $toDate, $branchId);
        $expenses = $this->getTotalExpenses($fromDate, $toDate, $branchId);

        return $this->mathService->subtract($revenue, $expenses);
    }

    /**
     * Get total expenses for a period.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalExpenses(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', 'Expense')->pluck('account_code')->toArray();

        if (empty($accountCodes)) {
            return '0';
        }

        $query = AccountLedger::query()
            ->whereIn('account_code', $accountCodes)
            ->whereBetween('entry_date', [$fromDate, $toDate])
            ->selectRaw('SUM(debit - credit) as net');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (string) ($query->value('net') ?? '0');
    }

    /**
     * Get total equity.
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalEquity(string $asOfDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', 'Equity')->pluck('account_code')->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get total assets.
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalAssets(string $asOfDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', AccountType::Asset->value)
            ->pluck('account_code')
            ->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    protected function getTotalLiabilities(string $asOfDate, ?int $branchId = null): string
    {
        $accountCodes = ChartOfAccount::where('account_type', AccountType::Liability->value)
            ->pluck('account_code')
            ->toArray();
        $balances = $this->getLatestBalances($accountCodes, $asOfDate, $branchId);

        $total = '0';
        foreach ($balances as $balance) {
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get the latest running balance for each account code as of a date.
     *
     * @param  array<int, string>  $accountCodes
     * @return array<string, string>
     */
    protected function getLatestBalances(array $accountCodes, string $asOfDate, ?int $branchId = null): array
    {
        if (empty($accountCodes)) {
            return [];
        }

        $query = AccountLedger::query()
            ->select('account_code')
            ->selectRaw('MAX(id) as max_id')
            ->whereIn('account_code', $accountCodes)
            ->whereRaw('DATE(entry_date) <= ?', [$asOfDate]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $maxIds = $query->groupBy('account_code')->pluck('max_id', 'account_code');

        if ($maxIds->isEmpty()) {
            return [];
        }

        // Use the Eloquent query builder (no eager-load of 'account' so we
        // avoid extra queries; AccountLedger::$with loads ['account'] by
        // default and would fire a select per group). Only running_balance
        // is needed here.
        $balances = AccountLedger::query()
            ->with([]) // override the default $with
            ->whereIn('id', $maxIds->values())
            ->pluck('running_balance', 'account_code');

        // running_balance can be NULL for ledger rows that were never populated;
        // coerce to '0' so bcmath never receives an empty string.
        return $balances->mapWithKeys(fn ($balance, $code) => [$code => (string) ($balance ?? '0')])->toArray();
    }
}
