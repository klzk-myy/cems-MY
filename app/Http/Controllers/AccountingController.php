<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\AccountingPeriod;
use App\Models\BankReconciliation;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\BankReconciliationService;
use App\Services\BudgetService;
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

    public function __construct(
        AccountingService $accountingService,
        BudgetService $budgetService,
        MathService $mathService,
        PeriodCloseService $periodCloseService,
        BankReconciliationService $bankReconciliationService
    ) {
        $this->accountingService = $accountingService;
        $this->budgetService = $budgetService;
        $this->mathService = $mathService;
        $this->periodCloseService = $periodCloseService;
        $this->bankReconciliationService = $bankReconciliationService;
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

    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        if ($entry->isReversed()) {
            return back()->with('error', 'Entry is already reversed.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

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

    public function closePeriod(Request $request, AccountingPeriod $period): RedirectResponse
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

    public function importBankStatement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'lines' => 'required|array|min:1',
            'lines.*.date' => 'required|date',
            'lines.*.reference' => 'nullable|string|max:255',
            'lines.*.description' => 'required|string|max:500',
            'lines.*.debit' => 'nullable|numeric|min=0',
            'lines.*.credit' => 'nullable|numeric|min=0',
        ]);

        $result = $this->bankReconciliationService->importStatement(
            $validated['account_code'],
            $validated['lines'],
            auth()->id()
        );

        return redirect()->route('accounting.reconciliation')
            ->with('success', "Imported {$result['imported']} lines. {$result['unmatched']} unmatched.");
    }

    public function markAsException(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->bankReconciliationService->markAsException($reconciliation->id, $validated['reason'], auth()->id());

        return redirect()->route('accounting.reconciliation')
            ->with('success', 'Item marked as exception.');
    }

    public function reconciliationReport(Request $request): View
    {
        $validated = $request->validate([
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $report = $this->bankReconciliationService->getReconciliationReport(
            $validated['account_code'],
            $validated['from'],
            $validated['to']
        );

        return view('accounting.reconciliation_report', compact('report'));
    }

    public function exportReconciliation(Request $request): View
    {
        $validated = $request->validate([
            'account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $report = $this->bankReconciliationService->getReconciliationReport(
            $validated['account_code'],
            $validated['from'],
            $validated['to']
        );

        return view('accounting.reconciliation_export', compact('report'));
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_code' => 'required|string',
            'budgets' => 'required|array|min:1',
            'budgets.*.account_code' => 'required|string|exists:chart_of_accounts,account_code',
            'budgets.*.amount' => 'required|numeric|min=0',
        ]);

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

    public function updateBudget(Request $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min=0',
        ]);

        $budget->update([
            'budget_amount' => $validated['amount'],
        ]);

        return redirect()->route('accounting.budget')
            ->with('success', 'Budget updated successfully.');
    }
}
