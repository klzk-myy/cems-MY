<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\BalanceSheetRequest;
use App\Http\Requests\Accounting\ClosePeriodRequest;
use App\Http\Requests\Accounting\ExportReconciliationRequest;
use App\Http\Requests\Accounting\ImportBankStatementRequest;
use App\Http\Requests\Accounting\LedgerRequest;
use App\Http\Requests\Accounting\MarkReconciliationExceptionRequest;
use App\Http\Requests\Accounting\ProfitLossRequest;
use App\Http\Requests\Accounting\ReconciliationReportRequest;
use App\Http\Requests\Accounting\ReverseJournalEntryRequest;
use App\Http\Requests\Accounting\StoreBudgetRequest;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Accounting\TrialBalanceRequest;
use App\Http\Requests\Accounting\UpdateBudgetRequest;
use App\Models\AccountingPeriod;
use App\Models\BankReconciliation;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\BankReconciliationService;
use App\Services\BudgetService;
use App\Services\LedgerService;
use App\Services\MathService;
use App\Services\PeriodCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AccountingController extends Controller
{
    protected AccountingService $accountingService;

    protected BudgetService $budgetService;

    protected MathService $mathService;

    protected PeriodCloseService $periodCloseService;

    protected BankReconciliationService $bankReconciliationService;

    protected LedgerService $ledgerService;

    public function __construct(
        AccountingService $accountingService,
        BudgetService $budgetService,
        MathService $mathService,
        PeriodCloseService $periodCloseService,
        BankReconciliationService $bankReconciliationService,
        LedgerService $ledgerService
    ) {
        $this->accountingService = $accountingService;
        $this->budgetService = $budgetService;
        $this->mathService = $mathService;
        $this->periodCloseService = $periodCloseService;
        $this->bankReconciliationService = $bankReconciliationService;
        $this->ledgerService = $ledgerService;
    }

    public function index(): View
    {
        $entries = JournalEntry::with('postedBy')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('accounting.journal.index', compact('entries'));
    }

    public function create(): View
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return view('accounting.journal.create', compact('accounts'));
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $entry = $this->accountingService->createJournalEntry(
                $validated['lines'],
                'Manual',
                null,
                $validated['description'],
                $validated['entry_date']
            );

            return redirect()->route('accounting.journal.show', $entry)
                ->with('success', 'Journal entry created successfully.');

        } catch (\InvalidArgumentException $e) {
            Log::warning('JournalEntry create failed', ['exception' => $e, 'description' => $request->input('description')]);

            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }
    }

    public function show(JournalEntry $entry): View
    {
        $entry->load('lines.account', 'postedBy', 'reversedBy');

        return view('accounting.journal.show', compact('entry'));
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $entry): RedirectResponse
    {
        if ($entry->isReversed()) {
            return back()->with('error', 'Entry is already reversed.');
        }

        $validated = $request->validated();

        try {
            $reversal = $this->accountingService->reverseJournalEntry(
                $entry,
                $validated['reason']
            );

            return redirect()->route('accounting.journal.show', $reversal)
                ->with('success', 'Entry reversed successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Reversal failed: '.$e->getMessage());
        }
    }

    public function periods(Request $request): View
    {
        $periods = AccountingPeriod::orderBy('start_date', 'desc')->paginate(12);

        return view('accounting.periods', compact('periods'));
    }

    public function closePeriod(ClosePeriodRequest $request, AccountingPeriod $period): RedirectResponse
    {
        try {
            $result = $this->periodCloseService->closePeriod($period, auth()->id());

            return redirect()->route('accounting.periods')
                ->with('success', "Period {$period->period_code} closed successfully");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function budget(Request $request): View
    {
        $periodCode = $request->get('period', now()->format('Y-m'));
        $report = $this->budgetService->getBudgetReport($periodCode);
        $unbudgeted = $this->budgetService->getAccountsWithoutBudget($periodCode);

        return view('accounting.budget', compact('report', 'unbudgeted', 'periodCode'));
    }

    public function reconciliation(Request $request): View
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

    public function reconciliationReport(ReconciliationReportRequest $request): View
    {
        $validated = $request->validated();

        $report = $this->bankReconciliationService->getReconciliationReport(
            $validated['account_code'],
            $validated['from'],
            $validated['to']
        );

        return view('accounting.reconciliation_report', compact('report'));
    }

    public function exportReconciliation(ExportReconciliationRequest $request): View
    {
        $validated = $request->validated();

        $report = $this->bankReconciliationService->getReconciliationReport(
            $validated['account_code'],
            $validated['from'],
            $validated['to']
        );

        return view('accounting.reconciliation_export', compact('report'));
    }

    public function storeBudget(StoreBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated['budgets'] as $budgetData) {
            $this->budgetService->setBudget(
                $budgetData['account_code'],
                $validated['period_code'],
                $budgetData['amount'],
                auth()->id()
            );
        }

        return redirect()->route('accounting.budget', ['period' => $validated['period_code']])
            ->with('success', 'Budget saved successfully.');
    }

    public function updateBudget(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validated();

        $budget->update([
            'budget_amount' => $validated['amount'],
        ]);

        return redirect()->route('accounting.budget')
            ->with('success', 'Budget updated successfully.');
    }

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

    public function fiscalYears(): View
    {
        $fiscalYears = FiscalYear::with('periods')
            ->orderBy('year_code', 'desc')
            ->get();

        return view('accounting.fiscal-years', compact('fiscalYears'));
    }

    public function revaluation(): View
    {
        return view('accounting.revaluation.index');
    }

    public function revaluationHistory(): View
    {
        return view('accounting.revaluation.history');
    }
}
