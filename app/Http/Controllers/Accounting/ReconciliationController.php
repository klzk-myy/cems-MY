<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ExportReconciliationRequest;
use App\Http\Requests\Accounting\ImportBankStatementRequest;
use App\Http\Requests\Accounting\MarkReconciliationExceptionRequest;
use App\Http\Requests\Accounting\ReconciliationReportRequest;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        protected BankReconciliationService $bankReconciliationService,
    ) {}

    public function index(Request $request): View
    {
        $cashAccounts = ChartOfAccount::where('account_type', 'Asset')
            ->where('account_name', 'like', '%Cash%')
            ->where('is_active', true)
            ->get();
        $accountCode = $request->get('account_code', $request->get('account', $cashAccounts->first()?->account_code));
        $fromDate = $request->get('from', now()->startOfMonth()->toDateString());
        $toDate = $request->get('to', now()->endOfMonth()->toDateString());
        $report = $this->bankReconciliationService->getReconciliationViewData(
            $accountCode,
            $fromDate,
            $toDate
        );

        return view('accounting.reconciliation', compact('report', 'cashAccounts'));
    }

    public function importBankStatement(ImportBankStatementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->bankReconciliationService->importStatement(
            $validated['account_code'],
            $validated['lines'],
            auth()->id()
        );

        return redirect()->route('accounting.reconciliation')
            ->with('success', "Imported {$result['imported']} lines. {$result['unmatched']} unmatched.");
    }

    public function markAsException(MarkReconciliationExceptionRequest $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $validated = $request->validated();

        $this->bankReconciliationService->markAsException($reconciliation->id, $validated['reason'], auth()->id());

        return redirect()->route('accounting.reconciliation')
            ->with('success', 'Item marked as exception.');
    }

    private function getReconciliationReport(Request $request): array
    {
        $validated = $request->validated();

        return $this->bankReconciliationService->getReconciliationReport(
            $validated['account_code'],
            $validated['from'],
            $validated['to']
        );
    }

    public function reconciliationReport(ReconciliationReportRequest $request): View
    {
        $report = $this->getReconciliationReport($request);

        return view('accounting.reconciliation_report', compact('report'));
    }

    public function exportReconciliation(ExportReconciliationRequest $request): View
    {
        $report = $this->getReconciliationReport($request);

        return view('accounting.reconciliation_export', compact('report'));
    }
}
