<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\FiscalYearClosedException;
use App\Exceptions\Domain\FiscalYearNotFoundException;
use App\Exceptions\Domain\OpenPeriodsException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Http\Requests\StoreFiscalYearRequest;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    protected FiscalYearService $fiscalYearService;

    public function __construct(FiscalYearService $fiscalYearService)
    {
        $this->fiscalYearService = $fiscalYearService;
    }

    /**
     * Display list of fiscal years.
     */
    public function index(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $fiscalYears = FiscalYear::orderBy('year_code', 'desc')->get();
        $yearReport = null;

        // If a year code is provided, get the report
        if ($request->has('year')) {
            $yearReport = $this->fiscalYearService->getYearEndReport($request->year);
        }

        return view('accounting.fiscal-years', compact('fiscalYears', 'yearReport'));
    }

    /**
     * Create a new fiscal year.
     */
    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        try {
            $year = $this->fiscalYearService->createFiscalYear(
                $request->year_code,
                $request->start_date,
                $request->end_date
            );

            return redirect()->back()->with('success', "Fiscal year {$year->year_code} created successfully.");
        } catch (\Exception $e) {
            Log::error('FiscalYear create failed', ['exception' => $e, 'year_code' => $request->year_code]);

            return redirect()->back()->with('error', "Failed to create fiscal year: {$e->getMessage()}");
        }
    }

    /**
     * Close a fiscal year.
     */
    public function close(FiscalYear $year, Request $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        // Verify confirmation code
        if ($request->confirm_code !== $year->year_code) {
            return redirect()->back()->with('error', 'Year code confirmation failed.');
        }

        try {
            $result = $this->fiscalYearService->closeFiscalYear($year);

            return redirect()->back()->with('success', "Fiscal year {$year->year_code} closed successfully. Net income: {$result['net_income']}");
        } catch (FiscalYearClosedException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (OpenPeriodsException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (PermissionDeniedException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            Log::error('FiscalYear close failed', ['exception' => $e, 'year_code' => $year->year_code]);

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('FiscalYear close failed', ['exception' => $e, 'year_code' => $year->year_code]);

            return redirect()->back()->with('error', "Failed to close fiscal year: {$e->getMessage()}");
        }
    }

    /**
     * Get year-end report for a fiscal year.
     */
    public function report(string $yearCode): View|RedirectResponse
    {
        $this->requireManagerOrAdmin();

        try {
            $yearReport = $this->fiscalYearService->getYearEndReport($yearCode);
            $fiscalYears = FiscalYear::orderBy('year_code', 'desc')->get();

            return view('accounting.fiscal-years', compact('fiscalYears', 'yearReport'));
        } catch (FiscalYearNotFoundException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('FiscalYear report failed', ['exception' => $e, 'year_code' => $yearCode]);

            return redirect()->back()->with('error', "Failed to generate report: {$e->getMessage()}");
        }
    }
}
