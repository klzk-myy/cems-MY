<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ExportReconciliationRequest;
use App\Http\Requests\Accounting\ImportBankStatementRequest;
use App\Http\Requests\Accounting\ManualMatchReconciliationRequest;
use App\Http\Requests\Accounting\MarkReconciliationExceptionRequest;
use App\Http\Requests\Accounting\ReconciliationIndexRequest;
use App\Http\Requests\Accounting\ReconciliationReportRequest;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        protected BankReconciliationService $bankReconciliationService,
    ) {}

    public function index(ReconciliationIndexRequest $request): View
    {
        // Reconcilable accounts are the Cash-class assets: till cash, banks
        // and nostro accounts. A name LIKE '%Cash%' filter used to miss the
        // "Bank (...)" and "Nostro (...)" accounts entirely.
        $cashAccounts = ChartOfAccount::where('account_type', 'Asset')
            ->where('account_class', 'Cash')
            ->where('is_active', true)
            ->get();
        $filters = $request->validated();

        $accountCode = $filters['account_code'] ?? $filters['account'] ?? $cashAccounts->first()?->account_code;
        $fromDate = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to'] ?? now()->endOfMonth()->toDateString();
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
            (int) auth()->id()
        );

        return redirect()->route('accounting.reconciliation')
            ->with('success', "Imported {$result['imported']} lines, skipped {$result['skipped']} duplicates. {$result['unmatched']} unmatched.");
    }

    public function markAsException(MarkReconciliationExceptionRequest $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $validated = $request->validated();

        $this->bankReconciliationService->markAsException($reconciliation->id, $validated['reason'], (int) auth()->id());

        return redirect()->route('accounting.reconciliation')
            ->with('success', 'Item marked as exception.');
    }

    private function getReconciliationReport(FormRequest $request): array
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

    /**
     * Manually match a reconciliation record to a journal entry.
     */
    public function manualMatch(ManualMatchReconciliationRequest $request, BankReconciliation $reconciliation): RedirectResponse
    {
        try {
            $this->bankReconciliationService->manualMatch($reconciliation->id, $request->validated('journal_entry_id'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.reconciliation')->with('success', 'Item matched to journal entry.');
    }
}
