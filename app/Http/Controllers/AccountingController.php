<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\ClosePeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * @deprecated Use JournalController, BudgetController, ReconciliationController, or ReportController instead.
 */
class AccountingController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected BudgetService $budgetService,
        protected MathService $mathService,
        protected PeriodCloseService $periodCloseService,
        protected BankReconciliationService $bankReconciliationService,
        protected LedgerService $ledgerService
    ) {}

    public function index(): View
    {
        $entries = JournalEntry::with(['lines', 'postedBy', 'creator', 'approver'])
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
