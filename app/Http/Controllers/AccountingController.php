<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\ClosePeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * @deprecated Use JournalController, BudgetController, ReconciliationController, or ReportController instead.
 */
class AccountingController extends Controller
{
    public function __construct(
        protected PeriodCloseService $periodCloseService,
    ) {}

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
            Log::error('Period close failed', ['error' => $e->getMessage()]);

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
