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

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

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

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->cashFlowService->getCashFlow($from, $to);

        return view('accounting.reports.cash-flow', compact('data', 'from', 'to'));
    }

    public function ratios(Request $request): View
    {
        $this->requireAccountingAccess();

        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);

        $accounts = collect($trialBalance['accounts'] ?? []);

        $totalAssets = '0';
        $totalLiabilities = '0';
        $currentAssets = '0';
        $currentLiabilities = '0';

        foreach ($accounts as $account) {
            $balance = (string) ($account['balance'] ?? '0');
            $type = $account['type'] ?? '';
            $category = $account['category'] ?? $account['account_type'] ?? '';

            if ($type === 'Asset' || str_starts_with($category, 'Asset')) {
                $totalAssets = $this->mathService->add($totalAssets, $balance);
                if (str_contains(strtolower($account['account_code'] ?? ''), '1')) {
                    $currentAssets = $this->mathService->add($currentAssets, $balance);
                }
            }
            if ($type === 'Liability' || str_starts_with($category, 'Liability')) {
                $totalLiabilities = $this->mathService->add($totalLiabilities, $balance);
                $currentLiabilities = $this->mathService->add($currentLiabilities, $balance);
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
