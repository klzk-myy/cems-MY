<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BalanceSheetRequest;
use App\Http\Requests\Accounting\LedgerRequest;
use App\Http\Requests\Accounting\ProfitLossRequest;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\LedgerService;
use App\Services\System\MathService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    use ResolvesBranchScope;

    public function __construct(
        protected CashFlowService $cashFlowService,
        protected LedgerService $ledgerService,
        protected MathService $mathService,
    ) {}

    /**
     * Selector payload for the report views: the resolved branch, whether the
     * user may switch branches, and the options list for those who may.
     *
     * @return array{currentBranch: ?Branch, canSelectBranch: bool, branches: Collection<int, Branch>}
     */
    protected function branchSelectorData(?int $branchId): array
    {
        $user = Auth::user();
        $canSelect = (bool) $user?->role->canManageAllBranches();

        return [
            'currentBranch' => $branchId !== null ? Branch::find($branchId) : null,
            'canSelectBranch' => $canSelect,
            'branches' => $canSelect
                ? Branch::where('is_active', true)->orderBy('name')->get()
                : collect(),
        ];
    }

    public function ledger(LedgerRequest $request): View
    {
        $validated = $request->validated();

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $accountCode = $validated['account_code'] ?? null;
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);

        $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();

        $ledger = null;
        if ($accountCode) {
            $ledger = $this->ledgerService->getAccountLedger($accountCode, $from, $to, $branchId);
        }

        return view('accounting.reports.ledger', [
            'ledger' => $ledger,
            'accounts' => $accounts,
            'from' => $from,
            'to' => $to,
            'accountCode' => $accountCode,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function ledgerAccount(Request $request, string $accountCode): View
    {
        $this->requireAccountingAccess();

        if (! preg_match('/^\d{4,6}$/', $accountCode)) {
            abort(422, 'Invalid account code format.');
        }

        $account = ChartOfAccount::where('account_code', $accountCode)->first();
        if (! $account) {
            abort(404, 'Account not found.');
        }

        $dates = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);

        $ledger = $this->ledgerService->getAccountLedger($accountCode, $from, $to, $branchId);

        return view('accounting.reports.ledger-account', [
            'ledger' => $ledger,
            'accountCode' => $accountCode,
            'from' => $from,
            'to' => $to,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function trialBalance(TrialBalanceRequest $request): View
    {
        $validated = $request->validated();

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate, $branchId);

        return view('accounting.reports.trial-balance', [
            'trialBalance' => $trialBalance,
            'asOfDate' => $asOfDate,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function profitLoss(ProfitLossRequest $request): View
    {
        $validated = $request->validated();

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);

        $report = $this->ledgerService->getProfitAndLoss($from, $to, $branchId);

        return view('accounting.reports.profit-loss', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function balanceSheet(BalanceSheetRequest $request): View
    {
        $validated = $request->validated();

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);
        $balanceSheet = $this->ledgerService->getBalanceSheet($asOfDate, $branchId);

        return view('accounting.reports.balance-sheet', [
            'balanceSheet' => $balanceSheet,
            'asOfDate' => $asOfDate,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function cashFlow(Request $request): View
    {
        $this->requireAccountingAccess();

        $dates = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);

        $data = $this->cashFlowService->getCashFlow($from, $to, $branchId);

        return view('accounting.reports.cash-flow', [
            'data' => $data,
            'from' => $from,
            'to' => $to,
            ...$this->branchSelectorData($branchId),
        ]);
    }

    public function ratios(Request $request): View
    {
        $this->requireAccountingAccess();

        $asOfDate = $request->validate([
            'as_of_date' => 'nullable|date',
        ])['as_of_date'] ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId(Auth::user(), $request);
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate, $branchId);

        $accounts = collect($trialBalance['accounts'] ?? []);
        $accountClasses = ChartOfAccount::pluck('account_class', 'account_code');

        $totalAssets = '0';
        $totalLiabilities = '0';
        $currentAssets = '0';
        $currentLiabilities = '0';

        foreach ($accounts as $account) {
            // Trial-balance 'balance' is net (debits - credits): positive for
            // debit-normal accounts, negative for credit-normal ones.
            $netBalance = (string) ($account['balance'] ?? '0');
            $type = $account['account_type'] ?? '';
            $class = $accountClasses[$account['account_code'] ?? ''] ?? null;

            if ($type === 'Asset') {
                $totalAssets = $this->mathService->add($totalAssets, $netBalance);
                // Current assets are the explicitly liquid classes (cash,
                // bank, nostro, forex inventory, receivables); unclassified
                // or long-term asset classes stay out of the numerator.
                if (in_array($class, ['Cash', 'Inventory', 'Receivable'], true)) {
                    $currentAssets = $this->mathService->add($currentAssets, $netBalance);
                }
            } elseif ($type === 'Liability') {
                $balance = $this->mathService->multiply($netBalance, '-1');
                $totalLiabilities = $this->mathService->add($totalLiabilities, $balance);
                // Payable-class liabilities are current; other classes
                // (e.g. long-term Debt) are excluded.
                if ($class === 'Payable') {
                    $currentLiabilities = $this->mathService->add($currentLiabilities, $balance);
                }
            }
        }

        $ratios = [
            'current_ratio' => $this->mathService->compare($currentLiabilities, '0') > 0
                ? $this->mathService->divide($currentAssets, $currentLiabilities)
                : 'N/A',
            'debt_ratio' => $this->mathService->compare($totalAssets, '0') > 0
                ? $this->mathService->divide($totalLiabilities, $totalAssets)
                : 'N/A',
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'current_assets' => $currentAssets,
            'current_liabilities' => $currentLiabilities,
        ];

        return view('accounting.reports.ratios', [
            'ratios' => $ratios,
            'trialBalance' => $trialBalance,
            'asOfDate' => $asOfDate,
            ...$this->branchSelectorData($branchId),
        ]);
    }
}
