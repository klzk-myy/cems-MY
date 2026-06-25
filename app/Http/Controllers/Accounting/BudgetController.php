<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreBudgetRequest;
use App\Http\Requests\Accounting\UpdateBudgetRequest;
use App\Models\Budget;
use App\Services\Accounting\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService,
    ) {}

    public function index(Request $request): View
    {
        $periodCode = $request->get('period', now()->format('Y-m'));
        $report = $this->budgetService->getBudgetReport($periodCode);
        $unbudgeted = $this->budgetService->getAccountsWithoutBudget($periodCode);

        return view('accounting.budget', compact('report', 'unbudgeted', 'periodCode'));
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
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

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $validated = $request->validated();

        $budget->update([
            'budget_amount' => $validated['amount'],
        ]);

        return redirect()->route('accounting.budget')
            ->with('success', 'Budget updated successfully.');
    }
}
