<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ClosePeriodRequest;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function __construct(
        protected PeriodCloseService $periodCloseService,
    ) {}

    public function periods(Request $request): View
    {
        $periods = AccountingPeriod::with('fiscalYear')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('search'), fn ($q) => $q->where('period_code', 'like', '%'.$request->string('search')->value().'%'))
            ->when($request->filled('fiscal_year'), fn ($q) => $q->whereHas(
                'fiscalYear', fn ($q2) => $q2->where('year_code', $request->string('fiscal_year')->value())
            ))
            ->orderBy('start_date', 'desc')
            ->paginate(12)
            ->withQueryString();

        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->pluck('year_code', 'year_code');
        $currencies = Currency::where('is_active', true)->orderBy('name')->pluck('name', 'code');

        return view('accounting.periods', compact('periods', 'fiscalYears', 'currencies'));
    }

    public function closePeriod(ClosePeriodRequest $request, AccountingPeriod $period): RedirectResponse
    {
        try {
            $result = $this->periodCloseService->closePeriod($period, (int) auth()->id());

            return redirect()->route('accounting.periods')
                ->with('success', "Period {$period->period_code} closed successfully");
        } catch (\Exception $e) {
            Log::error('Period close failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Period close failed. Please try again.');
        }
    }
}
