<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiscalYearCloseRequest;
use App\Http\Requests\StoreFiscalYearRequest;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalYearService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function __construct(
        protected FiscalYearService $fiscalYearService
    ) {}

    public function list(): View
    {
        $this->requireManagerOrAdmin();

        $fiscalYears = FiscalYear::with('periods')->orderBy('year_code', 'desc')->get();

        return view('accounting.fiscal-years', compact('fiscalYears'));
    }

    /**
     * Create a new fiscal year.
     */
    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

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
        } catch (\Exception $e) {
            Log::error('FiscalYear create failed', ['exception' => $e, 'year_code' => $request->year_code]);

            return redirect()->back()->with('error', 'Failed to create fiscal year. Please try again.');
        }
    }

    /**
     * Close a fiscal year.
     */
    public function close(FiscalYear $year, FiscalYearCloseRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        if ($validated['confirm_code'] !== $year->year_code) {
            return redirect()->back()->with('error', 'Year code confirmation failed.');
        }

        try {
            $result = $this->fiscalYearService->closeFiscalYear($year);

            return redirect()->back()->with('success', "Fiscal year {$year->year_code} closed successfully. Net income: {$result['net_income']}");
        } catch (\InvalidArgumentException $e) {
            Log::error('FiscalYear close failed', ['exception' => $e, 'year_code' => $year->year_code]);

            return redirect()->back()->with('error', 'Invalid fiscal year operation. Please check your input.');
        } catch (\Exception $e) {
            Log::error('FiscalYear close failed', ['exception' => $e, 'year_code' => $year->year_code]);

            return redirect()->back()->with('error', 'Failed to close fiscal year. Please try again.');
        }
    }
}
