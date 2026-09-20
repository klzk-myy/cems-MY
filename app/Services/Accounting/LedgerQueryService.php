<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Exceptions\Domain\AccountNotFoundException;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Services\System\MathService;
use Illuminate\Support\Carbon;

/**
 * Read-side ledger queries extracted from AccountingService: balances,
 * activity sums, and account-direction classification. The write path
 * (AccountingService::updateLedger) shares latestChainBalance() and
 * isDebitNormal() so writers and readers agree on chain order and sign
 * convention.
 */
class LedgerQueryService
{
    /**
     * Debit-normal account types for rows whose account_type is stored as a
     * raw string rather than the AccountType enum.
     */
    private const DEBIT_NORMAL_TYPES = ['Asset', 'Expense'];

    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * Determine if an already-fetched account row is debit-normal.
     * updateLedger() locks the account row for writing and reuses it here to
     * avoid a second ChartOfAccount query per journal line.
     */
    public function isDebitNormal(ChartOfAccount $account): bool
    {
        return $account->account_type instanceof AccountType
            ? $account->account_type->isDebitNormal()
            : in_array($account->account_type, self::DEBIT_NORMAL_TYPES);
    }

    /**
     * Determine if an account is a debit-balance account.
     *
     * @param  string  $accountCode  The account code to check
     * @return bool True if account type is Asset or Expense
     *
     * @throws AccountNotFoundException If account is not found
     */
    public function isDebitAccount(string $accountCode): bool
    {
        $account = ChartOfAccount::find($accountCode);
        if (! $account) {
            throw new AccountNotFoundException($accountCode);
        }

        return $this->isDebitNormal($account);
    }

    /**
     * Get the current balance for an account.
     *
     * Retrieves the running balance from the most recent ledger entry,
     * optionally filtered by an as-of date and branch. This is the single
     * canonical implementation; LedgerService, FiscalYearService and
     * FinancialRatioService all delegate here (previously each duplicated
     * this query).
     *
     * @param  string  $accountCode  The account code to query
     * @param  string|null  $asOfDate  Date in YYYY-MM-DD format (default: current date)
     * @param  int|null  $branchId  Optional branch ID to filter by. Null means all branches.
     * @return string Account balance as a string for precision
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null, ?int $branchId = null): string
    {
        // All-branches reads: running_balance chains are per-branch, so picking
        // the latest row across branches would return a single branch's
        // balance mislabeled as consolidated. Aggregate instead — the sum is
        // branch-agnostic and also immune to backdated-posting chain repairs.
        if ($branchId === null) {
            /** @var object{row_count:int, td:?string, tc:?string} $totals */
            $totals = AccountLedger::where('account_code', $accountCode)
                ->when($asOfDate, fn ($q) => $q->whereRaw('DATE(entry_date) <= ?', [$asOfDate]))
                ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(debit),0) as td, COALESCE(SUM(credit),0) as tc')
                ->first();

            // No ledger rows at all: plain '0' (matches the previous
            // no-entry behavior and needs no account lookup).
            if ((int) $totals->row_count === 0) {
                return '0';
            }

            $net = $this->mathService->subtract((string) $totals->td, (string) $totals->tc);

            // A zero net needs no sign flip — skip the account lookup so
            // unknown account codes still resolve to a zero balance.
            if ($this->mathService->compare($net, '0') === 0) {
                return $net;
            }

            return $this->isDebitAccount($accountCode)
                ? $net
                : $this->mathService->multiply($net, '-1');
        }

        return $this->latestChainBalance($accountCode, $branchId, $asOfDate);
    }

    /**
     * Latest running balance on the exact chain for one branch scope.
     * A null branchId means the unbranched chain only (not consolidated) —
     * writers must never borrow another branch's chain end.
     */
    public function latestChainBalance(string $accountCode, ?int $branchId, ?string $asOfDate = null): string
    {
        $query = AccountLedger::where('account_code', $accountCode)
            ->when(
                $branchId === null,
                fn ($q) => $q->whereNull('branch_id'),
                fn ($q) => $q->where('branch_id', $branchId)
            );

        if ($asOfDate) {
            // Use date function for cross-database compatibility
            // This ensures proper comparison regardless of datetime vs date storage
            $query->whereRaw('DATE(entry_date) <= ?', [$asOfDate]);
        }

        $lastEntry = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (string) $lastEntry->running_balance : '0';
    }

    /**
     * Get net account activity (change in balance) within a date range.
     *
     * Calculates the net movement of an account between two dates.
     * For expense accounts, this returns total debits minus credits.
     *
     * @param  string  $accountCode  The account code to query
     * @param  string  $startDate  Start date in YYYY-MM-DD format (inclusive)
     * @param  string  $endDate  End date in YYYY-MM-DD format (inclusive)
     * @return string Net activity amount as a string (positive = net debit, negative = net credit)
     */
    public function getAccountActivity(string $accountCode, string $startDate, string $endDate): string
    {
        $totals = AccountLedger::where('account_code', $accountCode)
            ->whereDate('entry_date', '>=', Carbon::parse($startDate)->toDateString())
            ->whereDate('entry_date', '<=', Carbon::parse($endDate)->toDateString())
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        // Net activity: debits - credits (expense-normal).
        return $this->mathService->subtract(
            (string) ($totals->total_debit ?? 0),
            (string) ($totals->total_credit ?? 0)
        );
    }

    /**
     * Get account activity for many account codes in a single query.
     *
     * @param  array<int, string>  $accountCodes
     * @return array<string, string>
     */
    public function getAccountsActivity(array $accountCodes, string $fromDate, string $toDate): array
    {
        if (empty($accountCodes)) {
            return [];
        }

        $rows = AccountLedger::query()
            ->select('account_code')
            ->selectRaw('SUM(debit - credit) as activity')
            ->whereIn('account_code', $accountCodes)
            ->whereDate('entry_date', '>=', Carbon::parse($fromDate)->toDateString())
            ->whereDate('entry_date', '<=', Carbon::parse($toDate)->toDateString())
            ->groupBy('account_code')
            ->pluck('activity', 'account_code')
            ->toArray();

        return array_map('strval', $rows);
    }
}
