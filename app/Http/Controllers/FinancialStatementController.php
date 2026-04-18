<?php

namespace App\Http\Controllers;

use App\Services\CashFlowService;
use App\Services\FinancialRatioService;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class FinancialStatementController extends Controller
{
    protected LedgerService $ledgerService;

    protected CashFlowService $cashFlowService;

    protected FinancialRatioService $ratioService;

    public function __construct(
        LedgerService $ledgerService,
        CashFlowService $cashFlowService,
        FinancialRatioService $ratioService
    ) {
        $this->ledgerService = $ledgerService;
        $this->cashFlowService = $cashFlowService;
        $this->ratioService = $ratioService;
    }

    public function trialBalance(Request $request)
    {
        $this->requireManagerOrAdmin();

        $asOfDate = $request->input('as_of', now()->toDateString());
        $trialBalance = $this->ledgerService->getTrialBalance($asOfDate);

        return view('accounting.trial-balance', compact('trialBalance', 'asOfDate'));
    }

    public function profitLoss(Request $request)
    {
        $this->requireManagerOrAdmin();

        $fromDate = $request->input('from', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to', now()->toDateString());

        $pl = $this->ledgerService->getProfitAndLoss($fromDate, $toDate);

        return view('accounting.profit-loss', compact('pl', 'fromDate', 'toDate'));
    }

    public function balanceSheet(Request $request)
    {
        $this->requireManagerOrAdmin();

        $asOfDate = $request->input('as_of', now()->toDateString());
        $balanceSheet = $this->ledgerService->getBalanceSheet($asOfDate);

        return view('accounting.balance-sheet', compact('balanceSheet', 'asOfDate'));
    }

    public function cashFlow(Request $request)
    {
        $this->requireManagerOrAdmin();

        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());
        $cashFlow = null;

        if ($request->has('from_date') && $request->has('to_date')) {
            $cashFlow = $this->cashFlowService->getCashFlowStatement($fromDate, $toDate);
        }

        return view('accounting.cash-flow', compact('cashFlow', 'fromDate', 'toDate'));
    }

    public function ratios(Request $request)
    {
        $this->requireManagerOrAdmin();

        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $ratios = null;

        if ($request->has('from_date') && $request->has('to_date')) {
            $ratios = $this->ratioService->getAllRatios($asOfDate, $fromDate, $toDate);
        }

        return view('accounting.ratios', compact('ratios', 'fromDate', 'toDate', 'asOfDate'));
    }
}
