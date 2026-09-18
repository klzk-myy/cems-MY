<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearStatus;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\RunRevaluationRequest;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\FiscalYear;
use App\Models\RevaluationEntry;
use App\Services\Accounting\RevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RevaluationController extends Controller
{
    public function __construct(
        protected RevaluationService $revaluationService
    ) {}

    public function index(): View
    {
        $this->requireAccountingAccess();

        $positions = CurrencyPosition::with('currency')->get();
        $status = $this->revaluationService->getRevaluationStatus(now()->format('Y-m'));

        return view('accounting.revaluation.index', compact('positions', 'status'));
    }

    public function run(RunRevaluationRequest $request): RedirectResponse
    {
        $this->requireAccountingAccess();

        try {
            $results = $this->revaluationService->runRevaluationWithJournal();

            return redirect()->route('accounting.revaluation')
                ->with('success', "Revaluation complete. {$results['positions_updated']} positions updated.");

        } catch (ValidationException|DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Revaluation failed. Please try again.');
        }
    }

    public function history(Request $request): View
    {
        $this->requireAccountingAccess();

        $month = $request->input('month', now()->format('Y-m'));
        $history = RevaluationEntry::whereMonth('revaluation_date', now()->parse($month)->month)
            ->whereYear('revaluation_date', now()->parse($month)->year)
            ->with(['currency', 'postedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $fiscalYears = FiscalYear::where('status', FiscalYearStatus::Open)->orderBy('start_date', 'desc')->pluck('year_code', 'year_code');
        $currencies = Currency::where('is_active', true)->orderBy('name')->pluck('name', 'code');

        return view('accounting.revaluation.history', compact('history', 'month', 'fiscalYears', 'currencies'));
    }
}
