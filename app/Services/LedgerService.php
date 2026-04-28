<?php

namespace App\Services;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ledger Service
 *
 * Provides comprehensive ledger and financial reporting functionality.
 * Generates trial balances, account ledgers, profit and loss statements,
 * and balance sheets with proper accounting treatment for different account types.
 *
 * This service handles the core financial reporting needs of the accounting system,
 * working with ChartOfAccount and AccountLedger models to produce accurate
 * financial statements. All monetary calculations use high-precision math
 * via the injected MathService to prevent floating-point errors.
 */
class LedgerService
{
    /**
     * Create a new LedgerService instance.
     *
     * @param  MathService  $mathService  Service for high-precision mathematical operations
     * @param  AccountingService  $accountingService  Service for accounting calculations and balance retrieval
     */
    public function __construct(
        protected MathService $mathService,
        protected AccountingService $accountingService
    ) {}

    /**
     * Generate a trial balance report as of a specific date.
     *
     * The trial balance lists all active accounts with their debit/credit balances,
     * verifying that total debits equal total credits. Credit-normal accounts
     * (Liabilities, Equity, Revenue) show positive balances as credits, while
     * debit-normal accounts (Assets, Expenses) show positive balances as debits.
     *
     * Example return structure:
     * ```
     * [
     *     'accounts' => [
     *         [
     *             'account_code' => '1000',
     *             'account_name' => 'Cash',
     *             'account_type' => 'Asset',
     *             'debit' => '5000.00',
     *             'credit' => '0',
     *             'balance' => '5000.00'
     *         ],
     *         // ... more accounts
     *     ],
     *     'total_debits' => '15000.00',
     *     'total_credits' => '15000.00',
     *     'total_balance' => '0',
     *     'is_balanced' => true,
     *     'as_of_date' => '2024-01-31'
     * ]
     * ```
     *
     * @param  string|null  $asOfDate  Date for balance calculation (YYYY-MM-DD format). Defaults to current date if null.
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches (consolidated view).
     * @return array{
     * accounts: array<int, array{account_code: string, account_name: string, account_type: string, debit: string, credit: string, balance: string}>,
     * total_debits: string,
     * total_credits: string,
     * total_balance: string,
     * is_balanced: bool,
     * as_of_date: string
     * } Trial balance data with accounts list, totals, and balance status
     */
    public function getTrialBalance(?string $asOfDate = null, ?int $branchId = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();
        $cacheKey = "trial_balance.{$asOfDate}.".($branchId ?? 'all');

        return Cache::tags(['ledger', 'trial-balance'])->remember($cacheKey, 60, function () use ($asOfDate, $branchId) {
            $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();
            $accountCodes = $accounts->pluck('account_code')->toArray();

            if (empty($accountCodes)) {
                return $this->emptyTrialBalance($asOfDate);
            }

            // Get latest running balance for each account as of the date using window function
            $subQuery = DB::table('account_ledger')
                ->select(
                    'account_code',
                    'running_balance',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY account_code ORDER BY entry_date DESC, id DESC) as rn')
                )
                ->where('entry_date', '<=', $asOfDate)
                ->when($branchId !== null, function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->whereIn('account_code', $accountCodes);

            $balances = DB::query()
                ->fromSub($subQuery, 'ranked')
                ->where('rn', 1)
                ->pluck('running_balance', 'account_code')
                ->toArray();

            $trialBalance = [];
            $totalDebits = '0';
            $totalCredits = '0';

            foreach ($accounts as $account) {
                $balance = $balances[$account->account_code] ?? '0';

                if (in_array($account->account_type, ['Liability', 'Equity', 'Revenue'])) {
                    $debit = $this->mathService->compare($balance, '0') < 0 ? $this->mathService->multiply($balance, '-1') : '0';
                    $credit = $this->mathService->compare($balance, '0') >= 0 ? $balance : '0';
                } else {
                    $debit = $this->mathService->compare($balance, '0') >= 0 ? $balance : '0';
                    $credit = $this->mathService->compare($balance, '0') < 0 ? $this->mathService->multiply($balance, '-1') : '0';
                }

                $trialBalance[] = [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                ];

                $totalDebits = $this->mathService->add($totalDebits, $debit);
                $totalCredits = $this->mathService->add($totalCredits, $credit);
            }

            $totalBalance = $this->mathService->subtract($totalDebits, $totalCredits);

            $totalsByType = [
                'Asset' => '0',
                'Liability' => '0',
                'Equity' => '0',
                'Revenue' => '0',
                'Expense' => '0',
            ];
            foreach ($trialBalance as $account) {
                $type = $account['account_type'];
                if (isset($totalsByType[$type])) {
                    $totalsByType[$type] = $this->mathService->add($totalsByType[$type], $account['balance']);
                }
            }

            return [
                'accounts' => $trialBalance,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'total_balance' => $totalBalance,
                'totals_by_type' => $totalsByType,
                'is_balanced' => $this->mathService->compare($totalDebits, $totalCredits) === 0,
                'as_of_date' => $asOfDate,
            ];
        });
    }

    private function emptyTrialBalance(string $asOfDate): array
    {
        return [
            'accounts' => [],
            'total_debits' => '0',
            'total_credits' => '0',
            'total_balance' => '0',
            'totals_by_type' => [
                'Asset' => '0',
                'Liability' => '0',
                'Equity' => '0',
                'Revenue' => '0',
                'Expense' => '0',
            ],
            'is_balanced' => true,
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Retrieve detailed ledger entries for a specific account within a date range.
     *
     * Returns the account information along with all journal entries, opening balance
     * (balance before the from date), closing balance (balance as of the to date),
     * and period totals. The entries are ordered chronologically by entry date and ID.
     *
     * Example return structure:
     * ```
     * [
     *     'account' => ChartOfAccount {...},
     *     'entries' => Collection<AccountLedger> [...],
     *     'opening_balance' => '1000.00',
     *     'closing_balance' => '2500.00',
     *     'total_debits' => 2000.00,
     *     'total_credits' => 500.00,
     *     'period' => [
     *         'from' => '2024-01-01',
     *         'to' => '2024-01-31'
     *     ]
     * ]
     * ```
     *
     * @param  string  $accountCode  Unique code of the account to retrieve ledger for
     * @param  string  $fromDate  Start date for the ledger period (YYYY-MM-DD format)
     * @param  string  $toDate  End date for the ledger period (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return array{
     * account: ChartOfAccount,
     * entries: Collection<int, AccountLedger>,
     * opening_balance: string,
     * closing_balance: string,
     * total_debits: float,
     * total_credits: float,
     * period: array{from: string, to: string}
     * } Account ledger data with entries and balance information
     */
    public function getAccountLedger(string $accountCode, string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $account = ChartOfAccount::findOrFail($accountCode);

        $query = AccountLedger::with('journalEntry')
            ->where('account_code', $accountCode)
            ->whereBetween('entry_date', [$fromDate, $toDate]);

        // Apply branch filter if specified
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $entries = $query->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        return [
            'account' => $account,
            'entries' => $entries,
            'opening_balance' => $this->getOpeningBalance($accountCode, $fromDate, $branchId),
            'closing_balance' => $this->getClosingBalance($accountCode, $toDate, $branchId),
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
            'period' => ['from' => $fromDate, 'to' => $toDate],
        ];
    }

    /**
     * Generate a Profit and Loss (Income) statement for a specific period.
     *
     * Calculates total revenues and expenses for the given date range,
     * then computes the net profit (revenue minus expenses). Positive net profit
     * indicates profit, negative indicates loss. Each revenue and expense account
     * is listed with its activity amount for the period.
     *
     * Example return structure:
     * ```
     * [
     *     'revenues' => [
     *         [
     *             'account_code' => '4000',
     *             'account_name' => 'Sales Revenue',
     *             'amount' => '50000.00'
     *         ],
     *         // ... more revenue accounts
     *     ],
     *     'total_revenue' => '50000.00',
     *     'expenses' => [
     *         [
     *             'account_code' => '5000',
     *             'account_name' => 'Rent Expense',
     *             'amount' => '10000.00'
     *         ],
     *         // ... more expense accounts
     *     ],
     *     'total_expenses' => '35000.00',
     *     'net_profit' => '15000.00',
     *     'period' => [
     *         'from' => '2024-01-01',
     *         'to' => '2024-01-31'
     *     ]
     * ]
     * ```
     *
     * @param  string  $fromDate  Start date for the P&L period (YYYY-MM-DD format)
     * @param  string  $toDate  End date for the P&L period (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return array{
     * revenues: array<int, array{account_code: string, account_name: string, amount: string}>,
     * total_revenue: string,
     * expenses: array<int, array{account_code: string, account_name: string, amount: string}>,
     * total_expenses: string,
     * net_profit: string,
     * period: array{from: string, to: string}
     * } Profit and Loss statement with revenues, expenses, and net profit
     */
    public function getProfitAndLoss(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $revenues = ChartOfAccount::where('account_type', 'Revenue')->get();
        $revenueData = [];
        $totalRevenue = '0';

        foreach ($revenues as $revenue) {
            $balance = $this->getAccountActivity($revenue->account_code, $fromDate, $toDate, $branchId);
            $revenueData[] = [
                'account_code' => $revenue->account_code,
                'account_name' => $revenue->account_name,
                'amount' => $balance,
            ];
            $totalRevenue = $this->mathService->add($totalRevenue, $balance);
        }

        $expenses = ChartOfAccount::where('account_type', 'Expense')->get();
        $expenseData = [];
        $totalExpenses = '0';

        foreach ($expenses as $expense) {
            $balance = $this->getAccountActivity($expense->account_code, $fromDate, $toDate, $branchId);
            $expenseData[] = [
                'account_code' => $expense->account_code,
                'account_name' => $expense->account_name,
                'amount' => $balance,
            ];
            $totalExpenses = $this->mathService->add($totalExpenses, $balance);
        }

        $netProfit = $this->mathService->subtract($totalRevenue, $totalExpenses);

        return [
            'revenues' => $revenueData,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenseData,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'period' => ['from' => $fromDate, 'to' => $toDate],
        ];
    }

    /**
     * Generate a Balance Sheet as of a specific date.
     *
     * Presents the financial position by listing all assets, liabilities, and equity
     * accounts with their balances. Verifies the accounting equation:
     * Assets = Liabilities + Equity. Returns individual account details for each
     * category along with totals and balance verification status.
     *
     * Example return structure:
     * ```
     * [
     *     'assets' => [
     *         [
     *             'account_code' => '1000',
     *             'account_name' => 'Cash',
     *             'balance' => '25000.00'
     *         ],
     *         // ... more asset accounts
     *     ],
     *     'total_assets' => '50000.00',
     *     'liabilities' => [
     *         [
     *             'account_code' => '2000',
     *             'account_name' => 'Accounts Payable',
     *             'balance' => '10000.00'
     *         ],
     *         // ... more liability accounts
     *     ],
     *     'total_liabilities' => '15000.00',
     *     'equity' => [
     *         [
     *             'account_code' => '3000',
     *             'account_name' => 'Retained Earnings',
     *             'balance' => '35000.00'
     *         ],
     *         // ... more equity accounts
     *     ],
     *     'total_equity' => '35000.00',
     *     'liabilities_plus_equity' => '50000.00',
     *     'is_balanced' => true,
     *     'as_of_date' => '2024-01-31'
     * ]
     * ```
     *
     * @param  string  $asOfDate  Date for balance sheet snapshot (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return array{
     * assets: array<int, array{account_code: string, account_name: string, balance: string}>,
     * total_assets: string,
     * liabilities: array<int, array{account_code: string, account_name: string, balance: string}>,
     * total_liabilities: string,
     * equity: array<int, array{account_code: string, account_name: string, balance: string}>,
     * total_equity: string,
     * liabilities_plus_equity: string,
     * is_balanced: bool,
     * as_of_date: string
     * } Balance sheet data with assets, liabilities, equity, and verification status
     */
    public function getBalanceSheet(string $asOfDate, ?int $branchId = null): array
    {
        $assets = ChartOfAccount::where('account_type', 'Asset')->get();
        $assetData = [];
        $totalAssets = '0';

        foreach ($assets as $asset) {
            $balance = $this->getAccountBalance($asset->account_code, $asOfDate, $branchId);
            $assetData[] = [
                'account_code' => $asset->account_code,
                'account_name' => $asset->account_name,
                'balance' => $balance,
            ];
            $totalAssets = $this->mathService->add($totalAssets, $balance);
        }

        $liabilities = ChartOfAccount::where('account_type', 'Liability')->get();
        $liabilityData = [];
        $totalLiabilities = '0';

        foreach ($liabilities as $liability) {
            $balance = $this->getAccountBalance($liability->account_code, $asOfDate, $branchId);
            $liabilityData[] = [
                'account_code' => $liability->account_code,
                'account_name' => $liability->account_name,
                'balance' => $balance,
            ];
            $totalLiabilities = $this->mathService->add($totalLiabilities, $balance);
        }

        $equities = ChartOfAccount::where('account_type', 'Equity')->get();
        $equityData = [];
        $totalEquity = '0';

        foreach ($equities as $equity) {
            $balance = $this->getAccountBalance($equity->account_code, $asOfDate, $branchId);
            $equityData[] = [
                'account_code' => $equity->account_code,
                'account_name' => $equity->account_name,
                'balance' => $balance,
            ];
            $totalEquity = $this->mathService->add($totalEquity, $balance);
        }

        $liabilitiesPlusEquity = $this->mathService->add($totalLiabilities, $totalEquity);

        return [
            'assets' => $assetData,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilityData,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equityData,
            'total_equity' => $totalEquity,
            'liabilities_plus_equity' => $liabilitiesPlusEquity,
            'is_balanced' => $this->mathService->compare($totalAssets, $liabilitiesPlusEquity) === 0,
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Calculate the opening balance for an account as of a specific date.
     *
     * Retrieves the running balance from the last ledger entry before the given date.
     * Returns '0' if no prior entries exist.
     *
     * @param  string  $accountCode  Unique code of the account
     * @param  string  $fromDate  Date from which to calculate opening balance (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return string Opening balance amount as a string
     */
    protected function getOpeningBalance(string $accountCode, string $fromDate, ?int $branchId = null): string
    {
        // Use <= to include entries ON the fromDate in opening balance.
        // This is intentional: entries recorded on the as-of date contribute to the opening balance.
        $query = AccountLedger::where('account_code', $accountCode)
            ->where('entry_date', '<=', $fromDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $entry = $query->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $entry ? (string) $entry->running_balance : '0';
    }

    /**
     * Get account balance as of a specific date.
     *
     * Retrieves the running balance from the most recent ledger entry.
     *
     * @param  string  $accountCode  Unique code of the account
     * @param  string  $asOfDate  Date for balance calculation (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return string Account balance as a string
     */
    protected function getAccountBalance(string $accountCode, string $asOfDate, ?int $branchId = null): string
    {
        $query = AccountLedger::where('account_code', $accountCode);

        if ($asOfDate) {
            $query->whereRaw('DATE(entry_date) <= ?', [$asOfDate]);
        }

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $lastEntry = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (string) $lastEntry->running_balance : '0';
    }

    /**
     * Calculate the closing balance for an account as of a specific date.
     *
     * @param  string  $accountCode  Unique code of the account
     * @param  string  $toDate  Date for which to calculate closing balance (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return string Closing balance amount as a string
     */
    protected function getClosingBalance(string $accountCode, string $toDate, ?int $branchId = null): string
    {
        return $this->getAccountBalance($accountCode, $toDate, $branchId);
    }

    /**
     * Calculate the net activity for an account within a date range.
     *
     * Computes the total activity by summing debits and credits for the period,
     * adjusting based on account type. For Expense accounts, activity equals
     * debits minus credits. For Revenue accounts, activity equals credits minus debits.
     *
     * @param  string  $accountCode  Unique code of the account
     * @param  string  $fromDate  Start date for activity calculation (YYYY-MM-DD format)
     * @param  string  $toDate  End date for activity calculation (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return string Net activity amount as a string
     */
    protected function getAccountActivity(string $accountCode, string $fromDate, string $toDate, ?int $branchId = null): string
    {
        $query = AccountLedger::where('account_code', $accountCode)
            ->whereBetween('entry_date', [$fromDate, $toDate]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $entries = $query->get();

        // Get account type to determine proper activity calculation
        $account = ChartOfAccount::find($accountCode);
        $accountType = $account ? $account->account_type : 'Asset';

        $activity = '0';
        foreach ($entries as $entry) {
            if ($accountType === 'Expense') {
                // Expense: activity = debits - credits (debit-normal)
                $activity = $this->mathService->add($activity, (string) $entry->debit);
                $activity = $this->mathService->subtract($activity, (string) $entry->credit);
            } else {
                // Revenue: activity = credits - debits (credit-normal)
                $activity = $this->mathService->add($activity, (string) $entry->credit);
                $activity = $this->mathService->subtract($activity, (string) $entry->debit);
            }
        }

        return $activity;
    }
}
