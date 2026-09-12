<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Services\Contracts\MathServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CashFlowService
{
    public function __construct(
        protected MathServiceInterface $math,
    ) {}

    public function getCashFlow(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $cacheKey = "cashflow:{$fromDate}:{$toDate}:{$branchId}";

        return Cache::tags(['reports', 'cash-flow'])->remember($cacheKey, 300, function () use ($fromDate, $toDate, $branchId) {
            $operatingActivities = $this->calculateOperatingActivities($fromDate, $toDate, $branchId);
            $investingActivities = $this->calculateInvestingActivities($fromDate, $toDate, $branchId);
            $financingActivities = $this->calculateFinancingActivities($fromDate, $toDate, $branchId);

            $cashFromOperations = $operatingActivities['total'];
            $cashFromInvesting = $investingActivities['total'];
            $cashFromFinancing = $financingActivities['total'];

            $netCashChange = $this->math->add(
                $this->math->add($cashFromOperations, $cashFromInvesting),
                $cashFromFinancing
            );

            return [
                'from' => $fromDate,
                'to' => $toDate,
                'operating_activities' => $operatingActivities,
                'investing_activities' => $investingActivities,
                'financing_activities' => $financingActivities,
                'net_change_in_cash' => $netCashChange,
                'operating_total' => $cashFromOperations,
                'investing_total' => $cashFromInvesting,
                'financing_total' => $cashFromFinancing,
            ];
        });
    }

    /**
     * Posted journal entries with lines+accounts for a period. Draft/Reversed/
     * Rejected entries are excluded so reversed pairs don't distort the flow.
     *
     * @return Collection<int, JournalEntry>
     */
    protected function postedEntries(string $fromDate, string $toDate, ?int $branchId): Collection
    {
        // whereDate (not whereBetween on the column): on drivers that store a
        // datetime string in this column, a bare 'YYYY-MM-DD' upper bound
        // lexically excludes same-day entries.
        return JournalEntry::whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->where('status', JournalEntryStatus::Posted->value)
            ->with(['lines.account'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();
    }

    protected function calculateOperatingActivities(string $fromDate, string $toDate, ?int $branchId): array
    {
        $entries = $this->postedEntries($fromDate, $toDate, $branchId);

        // Exact decimal arithmetic: the previous float subtraction fed rounded
        // values into the reported net income.
        $netIncome = $this->math->subtract($this->sumRevenueAccounts($entries), $this->sumExpenseAccounts($entries));

        $depreciation = $this->sumByAccountClass($entries, 'Expense', 'Depreciation');
        $amortization = $this->sumByAccountClass($entries, 'Expense', 'Amortization');

        $arChange = $this->calculateAccountClassChange('Receivable', $fromDate, $toDate, $branchId, true);
        $apChange = $this->calculateAccountClassChange('Payable', $fromDate, $toDate, $branchId, false);
        $inventoryChange = $this->calculateAccountClassChange('Inventory', $fromDate, $toDate, $branchId, true);

        $nonCashAdjustments = $this->math->add($depreciation, $amortization);
        $workingCapitalChanges = $this->math->add(
            $this->math->add($arChange, $apChange),
            $inventoryChange
        );

        $total = $this->math->add(
            $this->math->add($netIncome, $nonCashAdjustments),
            $workingCapitalChanges
        );

        return [
            'net_income' => $netIncome,
            'depreciation' => $depreciation,
            'amortization' => $amortization,
            'ar_change' => (string) $arChange,
            'ap_change' => (string) $apChange,
            'inventory_change' => (string) $inventoryChange,
            'total' => $total,
        ];
    }

    protected function calculateInvestingActivities(string $fromDate, string $toDate, ?int $branchId): array
    {
        $entries = $this->postedEntries($fromDate, $toDate, $branchId);

        $assetPurchases = $this->sumByAccountClass($entries, 'Asset', 'Fixed Asset');
        // Disposal proceeds are credits on a Revenue account; net credit side.
        $assetSales = $this->sumByAccountClass($entries, 'Revenue', 'Disposal', 'credit');

        $total = $this->math->subtract($assetSales, $assetPurchases);

        return [
            'asset_purchases' => $assetPurchases,
            'asset_sales' => $assetSales,
            'total' => $total,
        ];
    }

    protected function calculateFinancingActivities(string $fromDate, string $toDate, ?int $branchId): array
    {
        $entries = $this->postedEntries($fromDate, $toDate, $branchId);

        // Liability/Equity inflows are credit-normal; measuring debit-credit
        // inverted the sign and reported capital/debt issuance as outflows.
        $debtIssued = $this->sumByAccountClass($entries, 'Liability', 'Debt', 'credit');
        $equityIssued = $this->sumByAccountClass($entries, 'Equity', 'Capital', 'credit');
        $dividends = $this->sumByAccountClass($entries, 'Equity', 'Dividend');

        $total = $this->math->add($this->math->add($equityIssued, $debtIssued), $this->math->subtract('0', $dividends));

        return [
            'debt_issued' => $debtIssued,
            'equity_issued' => $equityIssued,
            'dividends' => $dividends,
            'total' => $total,
        ];
    }

    /**
     * Net revenue activity (credits minus debits) on Revenue accounts.
     * account_type is an AccountType enum on the model — comparing the cast
     * object to a raw string previously matched nothing and zeroed net income.
     */
    protected function sumRevenueAccounts($entries): string
    {
        return $entries->flatMap->lines
            ->filter(fn ($line) => $line->account?->account_type?->value === 'Revenue')
            ->reduce(
                fn (string $carry, $line) => $this->math->add(
                    $carry,
                    $this->math->subtract((string) $line->credit, (string) $line->debit)
                ),
                '0'
            );
    }

    /**
     * Net expense activity (debits minus credits) on Expense accounts.
     */
    protected function sumExpenseAccounts($entries): string
    {
        return $entries->flatMap->lines
            ->filter(fn ($line) => $line->account?->account_type?->value === 'Expense')
            ->reduce(
                fn (string $carry, $line) => $this->math->add(
                    $carry,
                    $this->math->subtract((string) $line->debit, (string) $line->credit)
                ),
                '0'
            );
    }

    /**
     * Net activity on one account class.
     *
     * @param  string  $normal  'debit' returns debits-credits, 'credit' returns credits-debits
     */
    protected function sumByAccountClass($entries, string $type, string $class, string $normal = 'debit'): string
    {
        // BCMath accumulation over decimal strings: collection sum() would
        // float-coerce decimal(18,4) columns and corrupt reporting output.
        $lines = $entries->flatMap->lines
            ->filter(fn ($line) => $line->account?->account_type?->value === $type && $line->account->account_class === $class);

        $debit = $lines->reduce(
            fn (string $carry, $line) => $this->math->add($carry, (string) $line->debit),
            '0'
        );

        $credit = $lines->reduce(
            fn (string $carry, $line) => $this->math->add($carry, (string) $line->credit),
            '0'
        );

        return $normal === 'credit'
            ? $this->math->subtract($credit, $debit)
            : $this->math->subtract($debit, $credit);
    }

    /**
     * Change in an account class's balance over the period.
     *
     * journal_lines has no entry_date column — the date and branch filters
     * must come from the parent journal_entries join (the previous direct
     * column reference threw a QueryException on every call).
     *
     * @param  bool  $isAsset  Assets: increase consumes cash (negated).
     *                         Liabilities: increase provides cash.
     */
    protected function calculateAccountClassChange(string $class, string $fromDate, string $toDate, ?int $branchId, bool $isAsset): string
    {
        $start = $this->accountClassBalance($class, null, $fromDate, $branchId);
        $end = $this->accountClassBalance($class, $fromDate, $toDate, $branchId);

        $change = $this->math->subtract($end, $start);

        return $isAsset ? $this->math->negate($change) : $change;
    }

    /**
     * Cumulative debit-minus-credit balance of an account class.
     * Pass $fromDate=null to measure "before $toDate" (start-of-period).
     */
    protected function accountClassBalance(string $class, ?string $fromDate, string $toDate, ?int $branchId): string
    {
        return (string) (DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.account_code', '=', 'chart_of_accounts.account_code')
            ->where('chart_of_accounts.account_class', $class)
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->when(
                $fromDate === null,
                fn ($q) => $q->whereDate('journal_entries.entry_date', '<', $toDate),
                fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $toDate)
            )
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
            ->value('balance') ?? '0');
    }
}
