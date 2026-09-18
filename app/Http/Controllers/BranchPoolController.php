<?php

namespace App\Http\Controllers;

use App\Enums\PoolRemittanceStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\PoolRemittance;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\PoolRemittanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchPoolController extends Controller
{
    public function __construct(
        protected BranchPoolService $poolService,
        protected PoolRemittanceService $remittanceService,
    ) {}

    /**
     * List pools for the authenticated user's branch.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $branch = $user->branch;
        $allBranches = $user->role->canManageAllBranches();

        $pools = $allBranches
            ? BranchPool::with('branch')->orderBy('branch_id')->orderBy('currency_code')->get()
            : ($branch instanceof Branch
                ? $this->poolService->getAllPoolsForBranch($branch)
                : collect());

        $branches = $allBranches ? Branch::orderBy('name')->get() : collect();
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('branch.pools.index', compact('pools', 'branches', 'currencies', 'allBranches'));
    }

    /**
     * Create an empty pool for a branch/currency pair. Any manage_stock user
     * may create one for their own branch; cross-branch creation requires the
     * manage_all_branches grant.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $allBranches = $user->role->canManageAllBranches();

        $validated = $request->validate([
            'branch_id' => [$allBranches ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ]);

        $branchId = $allBranches ? (int) $validated['branch_id'] : (int) $user->branch_id;

        if ($branchId <= 0) {
            return back()->with('error', 'Your account is not assigned to a branch.');
        }

        $exists = BranchPool::where('branch_id', $branchId)
            ->where('currency_code', $validated['currency_code'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'A pool already exists for this branch and currency.');
        }

        BranchPool::create([
            'branch_id' => $branchId,
            'currency_code' => $validated['currency_code'],
            'available_balance' => '0.0000',
            'allocated_balance' => '0.0000',
        ]);

        return back()->with('success', 'Pool created. Use Fund to add balance.');
    }

    /**
     * Show a single pool.
     */
    public function show(Request $request, BranchPool $branchPool): View
    {
        $this->authorizeBranchScope($request, (int) $branchPool->branch_id);

        $branchPool->load('branch');
        $branch = $branchPool->branch;

        $pendingInbound = PoolRemittance::with(['fromBranch', 'initiator'])
            ->where('to_branch_id', $branchPool->branch_id)
            ->where('currency_code', $branchPool->currency_code)
            ->where('status', PoolRemittanceStatus::Pending)
            ->orderByDesc('id')
            ->get();

        $pendingOutbound = PoolRemittance::with('toBranch')
            ->where('from_branch_id', $branchPool->branch_id)
            ->where('currency_code', $branchPool->currency_code)
            ->where('status', PoolRemittanceStatus::Pending)
            ->orderByDesc('id')
            ->get();

        $recentRemittances = PoolRemittance::with(['fromBranch', 'toBranch'])
            ->where(function ($query) use ($branchPool) {
                $query->where('from_branch_id', $branchPool->branch_id)
                    ->orWhere('to_branch_id', $branchPool->branch_id);
            })
            ->where('currency_code', $branchPool->currency_code)
            ->where('status', '!=', PoolRemittanceStatus::Pending)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // A trading branch remits to head office; head office picks which
        // trading branch receives the capital.
        $remitDestinations = $branch instanceof Branch && $branch->isHeadOffice()
            ? Branch::branches()->orderBy('name')->get()
            : Branch::headOffices()->get();

        return view('branch.pools.show', compact(
            'branchPool', 'pendingInbound', 'pendingOutbound', 'recentRemittances', 'remitDestinations'
        ));
    }

    /**
     * Fund a pool (manager/admin only).
     */
    public function fund(Request $request, BranchPool $branchPool): RedirectResponse
    {
        $this->authorizeBranchScope($request, (int) $branchPool->branch_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branch = $branchPool->branch;

        if (! $branch instanceof Branch) {
            return back()->with('error', 'This pool is not attached to a branch.');
        }

        try {
            $this->poolService->replenish(
                $branch,
                $branchPool->currency_code,
                (string) $validated['amount'],
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pool funded successfully.');
    }

    /**
     * Debit a pool (manager/admin only). The full amount must be covered —
     * the service runs the balance check under the pool row lock and
     * writes the audit event.
     */
    public function debit(Request $request, BranchPool $branchPool): RedirectResponse
    {
        $this->authorizeBranchScope($request, (int) $branchPool->branch_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branch = $branchPool->branch;

        if (! $branch instanceof Branch) {
            return back()->with('error', 'This pool is not attached to a branch.');
        }

        try {
            $this->poolService->debitOrFail(
                $branch,
                $branchPool->currency_code,
                (string) $validated['amount'],
                $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pool debited successfully.');
    }

    /**
     * Initiate a remittance out of this pool. Trading branches remit surplus
     * up to head office; head office remits capital down to a trading branch.
     * The value parks in the 2300 clearing account until the receiver
     * acknowledges.
     */
    public function remit(Request $request, BranchPool $branchPool): RedirectResponse
    {
        $this->authorizeBranchScope($request, (int) $branchPool->branch_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $from = $branchPool->branch;
        $to = Branch::whereKey((int) $validated['to_branch_id'])->firstOrFail();

        if (! $from instanceof Branch) {
            return back()->with('error', 'This pool is not attached to a branch.');
        }

        try {
            $remittance = $this->remittanceService->initiate(
                $from,
                $to,
                $branchPool->currency_code,
                (string) $validated['amount'],
                $request->user()->id,
                $validated['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Remittance {$remittance->remittance_number} initiated — awaiting acknowledgement by {$to->name}.");
    }

    /**
     * Acknowledge receipt of a pending remittance — credits this branch's
     * pool and clears the 2300 clearing leg. Restricted to the receiving
     * branch (or a cross-branch user).
     */
    public function acknowledgeRemittance(Request $request, PoolRemittance $poolRemittance): RedirectResponse
    {
        $this->authorizeBranchScope($request, (int) $poolRemittance->to_branch_id);

        try {
            $this->remittanceService->acknowledge($poolRemittance, $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Remittance {$poolRemittance->remittance_number} acknowledged — pool credited.");
    }

    /**
     * Cancel a pending remittance — returns the funds to the sender's pool
     * and reverses the 2300 clearing leg. Restricted to the sending branch
     * (or a cross-branch user).
     */
    public function cancelRemittance(Request $request, PoolRemittance $poolRemittance): RedirectResponse
    {
        $this->authorizeBranchScope($request, (int) $poolRemittance->from_branch_id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->remittanceService->cancel($poolRemittance, $request->user()->id, $validated['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Remittance {$poolRemittance->remittance_number} cancelled — funds returned to the sending pool.");
    }

    /**
     * Users without the manage_all_branches grant may only view and act on
     * pool/remittance resources belonging to their own branch.
     */
    private function authorizeBranchScope(Request $request, int $branchId): void
    {
        $user = $request->user();

        abort_unless(
            $user->role->canManageAllBranches()
                || $branchId === (int) $user->branch_id,
            403
        );
    }
}
