<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BalanceSheetRequest;
use App\Http\Requests\Accounting\LedgerRequest;
use App\Http\Requests\Accounting\ProfitLossRequest;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\LedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
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

    public function cashFlow(): View
    {
        return view('accounting.reports.cash-flow');
    }

    public function ratios(): View
    {
        return view('accounting.reports.ratios');
    }
}
