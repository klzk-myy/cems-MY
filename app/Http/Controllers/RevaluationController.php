<?php

namespace App\Http\Controllers;

use App\Models\CurrencyPosition;
use App\Models\RevaluationEntry;
use App\Services\RevaluationService;
use Illuminate\Http\Request;

class RevaluationController extends Controller
{
    protected RevaluationService $revaluationService;

    public function __construct(RevaluationService $revaluationService)
    {
        $this->revaluationService = $revaluationService;
    }

    protected function requireManagerOrAdmin(): void
    {
        if (! auth()->user()->isManager()) {
            abort(403, 'Unauthorized. Manager or Admin access required.');
        }
    }

    public function index()
    {
        $this->requireManagerOrAdmin();

        $positions = CurrencyPosition::with('currency')->get();
        $status = $this->revaluationService->getRevaluationStatus(now()->format('Y-m'));

        return view('accounting.revaluation.index', compact('positions', 'status'));
    }

    public function run(Request $request)
    {
        $this->requireManagerOrAdmin();

        try {
            $results = $this->revaluationService->runRevaluationWithJournal();

            return redirect()->route('accounting.revaluation.index')
                ->with('success', "Revaluation complete. {$results['positions_updated']} positions updated.");

        } catch (\Exception $e) {
            return back()->with('error', 'Revaluation failed: '.$e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $this->requireManagerOrAdmin();

        $month = $request->input('month', now()->format('Y-m'));
        $history = RevaluationEntry::whereMonth('revaluation_date', now()->parse($month)->month)
            ->whereYear('revaluation_date', now()->parse($month)->year)
            ->with(['currency', 'postedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('accounting.revaluation.history', compact('history', 'month'));
    }
}
