<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\Accounting\LedgerService;
use Illuminate\View\View;

/**
 * ChartOfAccountsController
 *
 * Read-only chart of accounts viewer (plan WS-C3). Lists every account with
 * its code, name, type and balance as of today. Balances come from a single
 * LedgerService::getTrialBalance call keyed by account code, so the page is
 * O(1) queries regardless of account count (no N+1).
 *
 * Accessible to Admin and Compliance Officer roles (route middleware);
 * each row links to the existing ledger drill-down at
 * accounting/ledger/{code} (manager/admin surface).
 */
class ChartOfAccountsController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService,
    ) {}

    public function index(): View
    {
        $accounts = ChartOfAccount::query()->orderBy('account_code')->paginate(50);

        $trialBalance = $this->ledgerService->getTrialBalance(now()->toDateString());

        $balances = collect($trialBalance['accounts'] ?? [])
            ->keyBy('account_code');

        return view('accounting.chart-of-accounts.index', [
            'accounts' => $accounts,
            'balances' => $balances,
            'asOfDate' => $trialBalance['as_of_date'],
        ]);
    }
}
