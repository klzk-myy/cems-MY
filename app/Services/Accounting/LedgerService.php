<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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
     * totals_by_type: array{Asset: string, Liability: string, Equity: string, Revenue: string, Expense: string},
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

            // Aggregate balances for all accounts as of the date using a single grouped query.
            $balances = $this->getAggregatedAccountBalances(null, $asOfDate, $branchId);

            $trialBalance = [];
            $totalDebits = '0';
            $totalCredits = '0';

            foreach ($accounts as $account) {
                $balance = $balances[$account->account_code] ?? '0';
                $accountType = $account->account_type instanceof AccountType ? $account->account_type->value : (string) $account->account_type;

                // Net balance is always debit-minus-credit: a positive net shows
                // in the debit column, a negative net in the credit column,
                // regardless of the account's normal side. The previous
                // type-branched logic displayed credit-normal balances in the
                // debit column and reported balanced books as unbalanced.
                $debit = $this->mathService->compare($balance, '0') >= 0 ? $balance : '0';
                $credit = $this->mathService->compare($balance, '0') < 0 ? $this->mathService->multiply($balance, '-1') : '0';

                $trialBalance[] = [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'account_type' => $accountType,
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
                $typeKey = $type instanceof \BackedEnum ? $type->value : (string) $type;
                if (isset($totalsByType[$typeKey])) {
                    $totalsByType[$typeKey] = $this->mathService->add($totalsByType[$typeKey], $account['balance']);
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

    /**
     * @return array{
     * accounts: array<int, array{account_code: string, account_name: string, account_type: string, debit: string, credit: string, balance: string}>,
     * total_debits: string,
     * total_credits: string,
     * total_balance: string,
     * totals_by_type: array{Asset: string, Liability: string, Equity: string, Revenue: string, Expense: string},
     * is_balanced: bool,
     * as_of_date: string
     * }
     */
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
     * total_debits: string,
     * total_credits: string,
     * period: array{from: string, to: string}
     * } Account ledger data with entries and balance information
     */
    public function getAccountLedger(string $accountCode, string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $account = ChartOfAccount::findOrFail($accountCode);

        $query = AccountLedger::with('journalEntry')
            ->where('account_code', $accountCode)
            ->whereDate('entry_date', '>=', Carbon::parse($fromDate)->toDateString())
            ->whereDate('entry_date', '<=', Carbon::parse($toDate)->toDateString());

        // Apply branch filter if specified
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $entries = $query->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Sum with bcmath: Collection::sum() casts DECIMAL strings to float,
        // losing precision on large monetary totals.
        $totalDebits = '0';
        $totalCredits = '0';
        foreach ($entries as $entry) {
            $totalDebits = $this->mathService->add($totalDebits, (string) $entry->debit);
            $totalCredits = $this->mathService->add($totalCredits, (string) $entry->credit);
        }

        return [
            'account' => $account,
            'entries' => $entries,
            'opening_balance' => $this->getOpeningBalance($accountCode, $fromDate, $branchId),
            'closing_balance' => $this->getClosingBalance($accountCode, $toDate, $branchId),
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
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

        // Get aggregated balances for all accounts in one query
        $allBalances = $this->getAggregatedAccountBalances($fromDate, $toDate, $branchId);

        foreach ($revenues as $revenue) {
            $net = $allBalances->get($revenue->account_code, '0');
            $balance = $this->mathService->multiply($net, '-1'); // credits - debits
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
            $net = $allBalances->get($expense->account_code, '0');
            $balance = $net;
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
        $liabilities = ChartOfAccount::where('account_type', 'Liability')->get();
        $equities = ChartOfAccount::where('account_type', 'Equity')->get();

        $allBalances = $this->getAggregatedAccountBalances(null, $asOfDate, $branchId);

        // Debit-normal sections report the raw net; credit-normal sections
        // flip the sign so credit balances show as positive.
        [$assetData, $totalAssets] = $this->sectionTotals($assets, $allBalances, '1');
        [$liabilityData, $totalLiabilities] = $this->sectionTotals($liabilities, $allBalances, '-1');
        [$equityData, $totalEquity] = $this->sectionTotals($equities, $allBalances, '-1');

        // Unclosed revenue/expense balances are the period's net income and
        // belong in equity — without them the sheet is off by exactly that
        // amount between period closes. The line reads zero once
        // PeriodCloseService has posted the closing entries.
        $netIncome = '0';
        foreach (ChartOfAccount::whereIn('account_type', ['Revenue', 'Expense'])->get(['account_code']) as $plAccount) {
            $netIncome = $this->mathService->add($netIncome, $allBalances->get($plAccount->account_code, '0'));
        }
        $netIncome = $this->mathService->multiply($netIncome, '-1');

        $equityData[] = [
            'account_code' => '',
            'account_name' => 'Net Income (Current Period)',
            'balance' => $netIncome,
            'amount' => $netIncome,
        ];
        $totalEquity = $this->mathService->add($totalEquity, $netIncome);

        $liabilitiesPlusEquity = $this->mathService->add($totalLiabilities, $totalEquity);

        return [
            'assets' => $assetData,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilityData,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equityData,
            'total_equity' => $totalEquity,
            'liabilities_plus_equity' => $liabilitiesPlusEquity,
            'total_liabilities_equity' => $liabilitiesPlusEquity,
            'is_balanced' => $this->mathService->compare($totalAssets, $liabilitiesPlusEquity) === 0,
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Build one balance-sheet section: a row per account plus the running
     * total. `$sign === '-1'` flips the net so credit-normal balances display
     * as positive; debit-normal sections keep the raw net (including the '0'
     * default for absent accounts, which would otherwise render as '0.0000').
     *
     * @param  \Illuminate\Support\Collection<int, ChartOfAccount>  $accounts
     * @param  \Illuminate\Support\Collection<string, string>  $balances
     * @return array{0: array<int, array{account_code: string, account_name: string, balance: string, amount: string}>, 1: string}
     */
    private function sectionTotals(\Illuminate\Support\Collection $accounts, \Illuminate\Support\Collection $balances, string $sign): array
    {
        $rows = [];
        $total = '0';

        foreach ($accounts as $account) {
            $balance = $balances->get($account->account_code, '0');

            if ($sign === '-1') {
                $balance = $this->mathService->multiply($balance, '-1');
            }

            $rows[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'balance' => $balance,
                'amount' => $balance,
            ];
            $total = $this->mathService->add($total, $balance);
        }

        return [$rows, $total];
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
        // Opening balance must be the balance BEFORE the from date: the entry
        // list already includes rows dated on the from date, so including them
        // here double-counts them in opening + movement != closing.
        // Delegates to getAccountBalance so consolidated views (branchId null)
        // aggregate across branches instead of picking a single branch's
        // chain tail — which made opening + movement diverge from closing.
        $before = Carbon::parse($fromDate)->subDay()->toDateString();

        return $this->getAccountBalance($accountCode, $before, $branchId);
    }

    /**
     * Get account balance as of a specific date.
     *
     * Delegates to the canonical AccountingService::getAccountBalance so the
     * running-balance lookup lives in exactly one place.
     *
     * @param  string  $accountCode  Unique code of the account
     * @param  string  $asOfDate  Date for balance calculation (YYYY-MM-DD format)
     * @param  int|null  $branchId  Optional branch ID to filter by
     * @return string Account balance as a string
     */
    public function getAccountBalance(string $accountCode, string $asOfDate, ?int $branchId = null): string
    {
        return $this->accountingService->getAccountBalance($accountCode, $asOfDate, $branchId);
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
     * Get aggregated account balances (debits and credits) for a period.
     *
     * Efficiently retrieves total debits and credits per account, and overall totals,
     * using a single aggregated query with GROUP BY. Results are cached for 60 seconds.
     *
     * @return array{total_debit: string, total_credit: string, accounts: array<int, array{account_code: string, account_name: string, debit: string, credit: string}>}
     */
    public function getAccountBalancesForPeriod(string $startDate, string $endDate, ?int $branchId = null): array
    {
        $cacheKey = sprintf('ledger.balances.%s.%s.%s', $startDate, $endDate, $branchId ?? 'all');
        $cacheTags = ['ledger', 'balances'];

        return Cache::tags($cacheTags)->remember($cacheKey, 60, function () use ($startDate, $endDate, $branchId) {
            // Eloquent query on AccountLedger joined to ChartOfAccount.
            // with([]) suppresses the default eager-load of 'account' so we
            // don't fire a second select per group.
            /** @var \Illuminate\Support\Collection<int, object{account_code:string, account_name:string, total_debit:?string, total_credit:?string}> $results */
            $results = AccountLedger::query()
                ->with([])
                ->join('chart_of_accounts', 'account_ledger.account_code', '=', 'chart_of_accounts.account_code')
                ->select(
                    'account_ledger.account_code',
                    'chart_of_accounts.account_name'
                )
                ->selectRaw('SUM(account_ledger.debit) as total_debit')
                ->selectRaw('SUM(account_ledger.credit) as total_credit')
                ->whereBetween('account_ledger.entry_date', [$startDate, $endDate])
                ->whereBranch($branchId)
                ->groupBy('account_ledger.account_code', 'chart_of_accounts.account_name')
                ->orderBy('account_ledger.account_code')
                ->get();

            $totalDebit = '0';
            $totalCredit = '0';
            $accounts = [];

            foreach ($results as $row) {
                $debit = (string) $row->total_debit;
                $credit = (string) $row->total_credit;

                $totalDebit = $this->mathService->add($totalDebit, $debit);
                $totalCredit = $this->mathService->add($totalCredit, $credit);

                $accounts[] = [
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'debit' => $debit,
                    'credit' => $credit,
                ];
            }

            return [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'accounts' => $accounts,
            ];
        });
    }

    /**
     * Get aggregated account balances (debit minus credit) for a date range or as of a date.
     *
     * @param  string|null  $fromDate  Start date (inclusive). Null means no lower bound.
     * @param  string|null  $toDate  End date (inclusive). Null means no upper bound.
     * @param  int|null  $branchId  Optional branch filter.
     * @return \Illuminate\Support\Collection Collection keyed by account_code with net balance (string).
     */
    protected function getAggregatedAccountBalances(?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, object{account_code:string, total_debit:?string, total_credit:?string}> $results */
        $results = AccountLedger::query()
            ->with([])
            ->select('account_code')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->entryDateBetween($fromDate, $toDate)
            ->whereBranch($branchId)
            ->groupBy('account_code')
            ->get();

        $balances = \Illuminate\Support\Collection::make();
        foreach ($results as $row) {
            $net = $this->mathService->subtract((string) $row->total_debit, (string) $row->total_credit);
            $balances->put($row->account_code, $net);
        }

        return $balances;
    }
}
