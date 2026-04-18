<?php

namespace App\Http\Controllers;

use App\Services\LedgerService;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    public function index()
    {
        $this->requireManagerOrAdmin();

        $trialBalance = $this->ledgerService->getTrialBalance();

        return view('accounting.ledger.index', compact('trialBalance'));
    }

    public function account(Request $request, string $accountCode)
    {
        $this->requireManagerOrAdmin();

        $fromDate = $request->input('from', now()->subMonth()->toDateString());
        $toDate = $request->input('to', now()->toDateString());

        $ledger = $this->ledgerService->getAccountLedger($accountCode, $fromDate, $toDate);

        return view('accounting.ledger.account', compact('ledger', 'fromDate', 'toDate'));
    }
}
