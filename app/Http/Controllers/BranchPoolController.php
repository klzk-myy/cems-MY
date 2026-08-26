<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchPool;
use App\Services\Branch\BranchPoolService;
use App\Services\System\MathService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchPoolController extends Controller
{
    public function __construct(
        protected BranchPoolService $poolService,
        protected MathService $mathService,
    ) {}

    /**
     * List pools for the authenticated user's branch.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $branch = $user->branch;

        $pools = $branch instanceof Branch
            ? $this->poolService->getAllPoolsForBranch($branch)
            : BranchPool::with('branch')->get();

        return view('branch-pools.index', compact('pools'));
    }

    /**
     * Show a single pool.
     */
    public function show(BranchPool $branchPool): View
    {
        $branchPool->load('branch');

        return view('branch-pools.show', compact('branchPool'));
    }

    /**
     * Fund a pool (manager/admin only).
     */
    public function fund(Request $request, BranchPool $branchPool): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branch = $branchPool->branch;

        if (! $branch instanceof Branch) {
            return back()->with('error', 'This pool is not attached to a branch.');
        }

        $this->poolService->replenish(
            $branch,
            $branchPool->currency_code,
            (string) $validated['amount'],
            $request->user()->id,
        );

        return back()->with('success', 'Pool funded successfully.');
    }

    /**
     * Debit a pool (manager/admin only).
     */
    public function debit(Request $request, BranchPool $branchPool): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (string) $validated['amount'];

        $pool = BranchPool::where('id', $branchPool->id)->lockForUpdate()->first();

        if ($this->mathService->compare($pool->available_balance, $amount) < 0) {
            return back()->with('error', 'Insufficient available balance in pool.');
        }

        $pool->available_balance = $this->mathService->subtract(
            $pool->available_balance,
            $amount
        );
        $pool->save();

        return back()->with('success', 'Pool debited successfully.');
    }
}
