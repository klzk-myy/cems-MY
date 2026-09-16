<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BalanceSheetRequest;
use App\Http\Requests\Accounting\LedgerRequest;
use App\Http\Requests\Accounting\ProfitLossRequest;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\LedgerService;
use App\Services\System\MathService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected CashFlowService $cashFlowService,
        protected LedgerService $ledgerService,
        protected MathService $mathService,
    ) {}

    public function ledger(LedgerRequest $request): View
    {
        $validated = $request->validated();

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $accountCode = $validated['account_code'] ?? null;

        $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();

        $ledger = null;
        if ($accountCode) {
            $ledger = $this->ledgerService->getAccountLedger($accountCode, $from, $to);
        }

        return view('accounting.reports.ledger', compact('ledger', 'accounts', 'from', 'to', 'accountCode'));
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

        $ledger = $this->ledgerService->getAccountLedger($accountCode, $from, $to);

        return view('accounting.reports.ledger-account', compact('ledger', 'accountCode', 'from', 'to'));
    }

    public function trialBalance(TrialBalanceRequest $request): View
    {
        $validated = $request->validated();

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);

        return view('accounting.reports.trial-balance', compact('trialBalance', 'asOfDate'));
    }

    public function profitLoss(ProfitLossRequest $request): View
    {
        $validated = $request->validated();

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $this->ledgerService->getProfitAndLoss($from, $to);

        return view('accounting.reports.profit-loss', compact('report', 'from', 'to'));
    }

    public function balanceSheet(BalanceSheetRequest $request): View
    {
        $validated = $request->validated();

        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
        $balanceSheet = $this->ledgerService->getBalanceSheet($asOfDate);

        return view('accounting.reports.balance-sheet', compact('balanceSheet', 'asOfDate'));
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

        $data = $this->cashFlowService->getCashFlow($from, $to);

        return view('accounting.reports.cash-flow', compact('data', 'from', 'to'));
    }

    public function ratios(Request $request): View
    {
        $this->requireAccountingAccess();

        $asOfDate = $request->validate([
            'as_of_date' => 'nullable|date',
        ])['as_of_date'] ?? now()->toDateString();
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);

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

        return view('accounting.reports.ratios', compact('ratios', 'trialBalance', 'asOfDate'));
    }
}
