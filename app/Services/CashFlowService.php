<?php

namespace App\Services;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Cache;

/**
 * Cash Flow Service
 *
 * Generates cash flow statements using the direct method:
 * Operating Activities: Cash receipts - Cash payments
 * Investing Activities: Purchase/sale of fixed assets, investments
 * Financing Activities: Proceeds/repayment of loans, dividends
 */
class CashFlowService
{
    /**
     * Math service for high-precision calculations.
     */
    protected MathService $mathService;

    /**
     * Account codes for cash accounts (bank accounts).
     * Loaded dynamically from ChartOfAccount table with fallback defaults.
     */
    protected array $cashAccounts = [];

    /**
     * Default cash accounts for fallback when DB is empty.
     */
    protected const DEFAULT_CASH_ACCOUNTS = [
        '1000', '1010', '1020', '1030', '1040', '1050', '1060', '1070', // Cash accounts
        '1100', '1110', '1120', '1130', // Bank accounts
    ];

    /**
     * Cache key for cash accounts lookup.
     */
    protected const CACHE_KEY_CASH_ACCOUNTS = 'cash_flow_cash_accounts';

    /**
     * Cache TTL in seconds (24 hours).
     */
    protected const CACHE_TTL = 86400;

    /**
     * Account codes for operating activities.
     * These are revenue/expense codes that are less likely to change.
     */
    protected array $operatingAccounts = [
        'revenue' => ['5000', '5010', '5020', '5200', '5300', '5400'], // Revenue accounts
        'expense' => ['6000', '6010', '6100', '6200', '6210', '6220', '6230', '6300', '6310', '6320', '6330', '6400', '6410', '6500', '6510', '6520', '6530'],
    ];

    /**
     * Account codes for investing activities.
     */
    protected array $investingAccounts = [
        'asset_purchase' => ['2200'], // Security deposits
    ];

    /**
     * Account codes for financing activities.
     */
    protected array $financingAccounts = [
        'loans_received' => [],
        'loan_repayment' => [],
        'dividends' => [],
    ];

    /**
     * Create a new CashFlowService instance.
     */
    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
        $this->cashAccounts = $this->getCashAccounts();
    }

    /**
     * Get cash account codes dynamically from ChartOfAccount table.
     *
     * Caches the result for performance. Falls back to default accounts
     * if no cash accounts are found in the database.
     *
     * @return array Array of cash account codes
     */
    protected function getCashAccounts(): array
    {
        return Cache::remember(self::CACHE_KEY_CASH_ACCOUNTS, self::CACHE_TTL, function () {
            // Query cash accounts: Asset type with account_class 'Cash' or account_code starting with '1'
            $cashAccounts = ChartOfAccount::where('account_type', 'Asset')
                ->where(function ($query) {
                    $query->where('account_class', 'Cash')
                        ->orWhere('account_code', 'LIKE', '1%');
                })
                ->where('is_active', true)
                ->pluck('account_code')
                ->toArray();

            // If no cash accounts found in DB, use defaults as fallback
            if (empty($cashAccounts)) {
                return self::DEFAULT_CASH_ACCOUNTS;
            }

            return $cashAccounts;
        });
    }

    /**
     * Clear the cash accounts cache.
     * Call this when chart of accounts is modified.
     */
    public function clearCashAccountsCache(): bool
    {
        return Cache::forget(self::CACHE_KEY_CASH_ACCOUNTS);
    }

    /**
     * Get complete cash flow statement.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return array Cash flow statement data
     */
    public function getCashFlowStatement(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'branch_id' => $branchId,
            'operating' => $this->getOperatingCashFlow($fromDate, $toDate, $branchId),
            'investing' => $this->getInvestingCashFlow($fromDate, $toDate, $branchId),
            'financing' => $this->getFinancingCashFlow($fromDate, $toDate, $branchId),
            'net_change' => $this->getNetCashChange($fromDate, $toDate, $branchId),
            'opening_balance' => $this->getOpeningCashBalance($fromDate, $branchId),
            'closing_balance' => $this->getClosingCashBalance($toDate, $branchId),
        ];
    }

    /**
     * Get operating cash flow.
     *
     * Cash from customers - Cash paid to suppliers/employees/other
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getOperatingCashFlow(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $cashReceived = $this->getCashReceivedFromCustomers($fromDate, $toDate, $branchId);
        $cashPaidToSuppliers = $this->getCashPaidToSuppliers($fromDate, $toDate, $branchId);
        $cashPaidForSalaries = $this->getCashPaidForSalaries($fromDate, $toDate, $branchId);
        $cashPaidForExpenses = $this->getCashPaidForOtherExpenses($fromDate, $toDate, $branchId);

        $netOperating = $this->mathService->subtract(
            $this->mathService->add($cashReceived, $cashPaidToSuppliers),
            $this->mathService->add($cashPaidForSalaries, $cashPaidForExpenses)
        );

        return [
            'cash_from_customers' => $cashReceived,
            'cash_paid_to_suppliers' => $cashPaidToSuppliers,
            'cash_paid_for_salaries' => $cashPaidForSalaries,
            'cash_paid_for_expenses' => $cashPaidForExpenses,
            'net_operating' => $netOperating,
        ];
    }

    /**
     * Get investing cash flow.
     *
     * Purchase/sale of assets, investment income
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getInvestingCashFlow(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $assetPurchases = $this->getAssetPurchases($fromDate, $toDate, $branchId);
        $assetSales = $this->getAssetSales($fromDate, $toDate, $branchId);
        $investmentIncome = $this->getInvestmentIncome($fromDate, $toDate, $branchId);

        $netInvesting = $this->mathService->add(
            $this->mathService->subtract($assetSales, $assetPurchases),
            $investmentIncome
        );

        return [
            'asset_purchases' => $assetPurchases,
            'asset_sales' => $assetSales,
            'investment_income' => $investmentIncome,
            'net_investing' => $netInvesting,
        ];
    }

    /**
     * Get financing cash flow.
     *
     * Proceeds/repayment of loans, dividends
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getFinancingCashFlow(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $loansReceived = $this->getLoansReceived($fromDate, $toDate, $branchId);
        $loanRepayments = $this->getLoanRepayments($fromDate, $toDate, $branchId);
        $dividendsPaid = $this->getDividendsPaid($fromDate, $toDate, $branchId);

        $netFinancing = $this->mathService->subtract(
            $this->mathService->subtract($loansReceived, $loanRepayments),
            $dividendsPaid
        );

        return [
            'loans_received' => $loansReceived,
            'loan_repayments' => $loanRepayments,
            'dividends_paid' => $dividendsPaid,
            'net_financing' => $netFinancing,
        ];
    }

    /**
     * Get net cash change for period.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getNetCashChange(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $operating = $this->getOperatingCashFlow($fromDate, $toDate, $branchId);
        $investing = $this->getInvestingCashFlow($fromDate, $toDate, $branchId);
        $financing = $this->getFinancingCashFlow($fromDate, $toDate, $branchId);

        return $this->mathService->add(
            $this->mathService->add($operating['net_operating'], $investing['net_investing']),
            $financing['net_financing']
        );
    }

    /**
     * Get opening cash balance as of a date.
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getOpeningCashBalance(string $asOfDate, ?int $branchId = null): string
    {
        $total = '0';

        foreach ($this->cashAccounts as $accountCode) {
            $balance = $this->getAccountBalance($accountCode, $asOfDate, $branchId);
            $total = $this->mathService->add($total, $balance);
        }

        return $total;
    }

    /**
     * Get closing cash balance as of a date.
     *
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    public function getClosingCashBalance(string $asOfDate, ?int $branchId = null): string
    {
        return $this->getOpeningCashBalance($asOfDate, $branchId);
    }

    /**
     * Get cash received from customers.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCashReceivedFromCustomers(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        // Simplified: Total revenue * estimated cash collection ratio
        // In a real system, you'd track this more precisely via AR
        $totalRevenue = $this->getTotalForAccounts($this->operatingAccounts['revenue'], $fromDate, $toDate, 'credit', $branchId);
        $totalExpense = $this->getTotalForAccounts($this->operatingAccounts['expense'], $fromDate, $toDate, 'debit', $branchId);

        // Net operating cash flow approximation
        return $this->mathService->subtract($totalRevenue, $totalExpense);
    }

    /**
     * Get cash paid to suppliers.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCashPaidToSuppliers(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        // Cash paid = Debits to AP - Credits from AP
        // Simplified: COGS amount as proxy
        return $this->getTotalForAccounts(['6000', '6010'], $fromDate, $toDate, 'debit', $branchId);
    }

    /**
     * Get cash paid for salaries.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCashPaidForSalaries(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $salaryAccounts = ['6200', '6210', '6220', '6230']; // Salaries, EPF, EIS, SOCSO

        return $this->getTotalForAccounts($salaryAccounts, $fromDate, $toDate, 'debit', $branchId);
    }

    /**
     * Get cash paid for other operating expenses.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getCashPaidForOtherExpenses(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $expenseAccounts = array_diff($this->operatingAccounts['expense'], ['6200', '6210', '6220', '6230']);

        return $this->getTotalForAccounts(array_values($expenseAccounts), $fromDate, $toDate, 'debit', $branchId);
    }

    /**
     * Get asset purchases.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getAssetPurchases(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return $this->getTotalForAccounts($this->investingAccounts['asset_purchase'], $fromDate, $toDate, 'debit', $branchId);
    }

    /**
     * Get asset sales proceeds.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getAssetSales(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return $this->getTotalForAccounts($this->investingAccounts['asset_purchase'], $fromDate, $toDate, 'credit', $branchId);
    }

    /**
     * Get investment income received.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getInvestmentIncome(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return $this->getTotalForAccounts(['5300'], $fromDate, $toDate, 'debit', $branchId);
    }

    /**
     * Get loans received.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getLoansReceived(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return '0'; // No loan accounts currently
    }

    /**
     * Get loan repayments.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getLoanRepayments(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return '0'; // No loan accounts currently
    }

    /**
     * Get dividends paid.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getDividendsPaid(string $fromDate, string $toDate, ?int $branchId = null): string
    {
        return '0'; // No dividend accounts currently
    }

    /**
     * Get total debits or credits for a set of accounts.
     *
     * @param  string  $fromDate  Start date (YYYY-MM-DD)
     * @param  string  $toDate  End date (YYYY-MM-DD)
     * @param  string  $type  'debit' or 'credit'
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getTotalForAccounts(array $accountCodes, string $fromDate, string $toDate, string $type, ?int $branchId = null): string
    {
        $total = '0';

        foreach ($accountCodes as $accountCode) {
            $query = AccountLedger::where('account_code', $accountCode)
                ->whereBetween('entry_date', [$fromDate, $toDate]);

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            if ($type === 'debit') {
                $amount = $query->sum('debit');
            } else {
                $amount = $query->sum('credit');
            }

            $total = $this->mathService->add($total, (string) $amount);
        }

        return $total;
    }

    /**
     * Get account balance as of a date.
     *
     * @param  string  $accountCode  Account code
     * @param  string  $asOfDate  Date for balance calculation
     * @param  int|null  $branchId  Optional branch ID to filter by
     */
    protected function getAccountBalance(string $accountCode, string $asOfDate, ?int $branchId = null): string
    {
        $query = AccountLedger::where('account_code', $accountCode)
            ->whereRaw('DATE(entry_date) <= ?', [$asOfDate]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $lastEntry = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (string) $lastEntry->running_balance : '0';
    }
}
