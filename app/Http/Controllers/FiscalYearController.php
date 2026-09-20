<?php

namespace App\Http\Controllers;

use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Requests\FiscalYearCloseRequest;
use App\Http\Requests\StoreFiscalYearRequest;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalYearService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected FiscalYearService $fiscalYearService
    ) {}

    public function list(): View
    {
        $this->requireAccountingAccess();

        $allYears = FiscalYear::with('periods')->orderBy('year_code', 'desc')->get();

        // The "active" year is the one containing today; fall back to the
        // latest year when today is outside every configured year.
        $activeYear = $allYears->first(
            fn ($y) => now()->between($y->start_date, $y->end_date)
        ) ?? $allYears->first();

        $fiscalYears = $allYears->paginate(25);

        return view('accounting.fiscal-years', compact('fiscalYears', 'activeYear'));
    }

    /**
     * Create a new fiscal year.
     */
    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $this->requireAccountingAccess();

        try {
            // When no explicit dates are given, derive the fiscal year from the
            // configured year-end (FISCAL_YEAR_END_MONTH/DAY, default 31 Dec).
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            if (! $startDate || ! $endDate) {
                $year = (int) ($request->input('year') ?: now()->year);
                $endMonth = (int) config('accounting.fiscal_year_end_month', 12);
                $endDay = (int) config('accounting.fiscal_year_end_day', 31);

                $endDate = $endDate ?: Carbon::create($year, $endMonth, $endDay)->toDateString();
                $startDate = $startDate ?: Carbon::parse($endDate)->subYear()->addDay()->toDateString();
            }

            $year = $this->fiscalYearService->createFiscalYear(
                $request->year_code,
                $startDate,
                $endDate
            );

            return redirect()->back()->with('success', "Fiscal year {$year->year_code} created successfully.");
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'FiscalYear create failed', 'Failed to create fiscal year. Please try again.', ['year_code' => $request->year_code]);
        }
    }

    /**
     * Close a fiscal year.
     */
    public function close(FiscalYear $year, FiscalYearCloseRequest $request): RedirectResponse
    {
        $this->requireAccountingAccess();

        $validated = $request->validated();

        if ($validated['confirm_code'] !== $year->year_code) {
            return redirect()->back()->with('error', 'Year code confirmation failed.');
        }

        try {
            $result = $this->fiscalYearService->closeFiscalYear($year);

            return redirect()->back()->with('success', "Fiscal year {$year->year_code} closed successfully. Net income: {$result['net_income']}");
        } catch (\Throwable $e) {
            // Domain failures carry actionable messages (already closed,
            // open periods remaining, permission denied) — the global
            // DomainException renderer surfaces them via back()+flash.
            return $this->handleExceptionWeb($e, 'FiscalYear close failed', 'Failed to close fiscal year. Please try again.', ['year_code' => $year->year_code]);
        }
    }
}
