<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountCode;
use App\Enums\Permission;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InsufficientPettyCashException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branch petty-cash expenses.
 *
 * Holders of post_expenses post against their own branch float;
 * cross-branch roles (accountant, admin) may post for any branch. No
 * approval step — posting is direct, same as journals.
 */
class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $expenses = Expense::with(['branch', 'creator'])
            ->when(! $user->role->canManageAllBranches(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25);

        $branch = $user->role->canManageAllBranches() ? null : $user->branch;

        return view('accounting.expenses.index', [
            'expenses' => $expenses,
            'currentBranch' => $branch,
            'pettyCashFloat' => $branch?->petty_cash_float,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if (! $user->role->canPerform(Permission::PostExpenses)) {
            abort(403, 'Only users with the Post Branch Expenses permission can post expenses');
        }

        return view('accounting.expenses.create', [
            'expenseAccounts' => $this->expenseAccounts(),
            'branches' => $user->role->canManageAllBranches()
                ? Branch::where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch]),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->role->canPerform(Permission::PostExpenses)) {
            abort(403, 'Only users with the Post Branch Expenses permission can post expenses');
        }

        $validated = $request->validated();

        // Branch-scoped users may only post against their own branch float.
        $branch = $user->role->canManageAllBranches()
            ? Branch::findOrFail($validated['branch_id'])
            : $user->branch;

        if (! $branch instanceof Branch) {
            return back()->withInput()->withErrors(['branch_id' => 'You are not assigned to a branch.']);
        }

        try {
            $this->expenseService->postExpense(
                $branch,
                $user,
                $validated['account_code'],
                $validated['category'],
                $validated['description'],
                $validated['amount'],
                $validated['expense_date'] ?? null
            );
        } catch (InsufficientPettyCashException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (DomainException $e) {
            throw $e;
        }

        return redirect()->route('accounting.expenses.index')
            ->with('success', 'Expense posted successfully.');
    }

    /**
     * Top up a branch petty-cash float from company cash. Admin only —
     * funding moves company money into branch floats.
     */
    public function fund(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(403, 'Only admins can fund petty cash floats');
        }

        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'amount' => 'required|numeric|min:0.0001',
            'description' => 'nullable|string|max:500',
        ]);

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail((int) $validated['branch_id']);

        $this->expenseService->fundPettyCash($branch, $user, $validated['amount'], $validated['description'] ?? null);

        return redirect()->route('accounting.expenses.index')
            ->with('success', "Petty cash float funded for {$branch->name}.");
    }

    /**
     * Expense accounts selectable in the form (Expense category only).
     *
     * @return array<int|string, string>
     */
    private function expenseAccounts(): array
    {
        return collect(AccountCode::cases())
            ->filter(fn (AccountCode $code) => $code->category() === 'Expense')
            ->mapWithKeys(fn (AccountCode $code) => [$code->value => "{$code->value} — {$code->description()}"])
            ->all();
    }
}
