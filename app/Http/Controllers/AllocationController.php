<?php

namespace App\Http\Controllers;

use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AllocationController extends Controller
{
    public function __construct(
        protected TellerAllocationService $allocationService,
        protected TillService $tillService,
    ) {}

    /**
     * List allocations visible to the authenticated manager/admin.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $status = $request->query('status', 'active');
        $branch = $user->branch;

        $query = TellerAllocation::with(['user', 'branch', 'approver', 'counter', 'currency'])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id));

        match ($status) {
            'pending' => $query->where('status', TellerAllocationStatus::PENDING),
            'approved' => $query->where('status', TellerAllocationStatus::APPROVED),
            'active' => $query->where('status', TellerAllocationStatus::ACTIVE),
            'completed' => $query->where('status', TellerAllocationStatus::CLOSED),
            'rejected' => $query->where('status', TellerAllocationStatus::REJECTED),
            default => $query,
        };

        $allocations = $query->latest()->paginate(25);

        return view('allocations.index', compact('allocations', 'status'));
    }

    /**
     * Show a single allocation.
     */
    public function show(TellerAllocation $allocation): View
    {
        $allocation->load(['user', 'branch', 'approver', 'counter', 'currency']);

        return view('allocations.show', compact('allocation'));
    }

    /**
     * Approve a pending allocation request (manager/admin, own branch).
     */
    public function approve(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        $this->authorizeAllocationBranch($request, $allocation);

        $validated = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0.0001'],
            'daily_limit_myr' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! $allocation->isPending()) {
            return back()->with('error', 'Allocation is not pending approval.');
        }

        try {
            $this->allocationService->approveAllocation(
                $allocation,
                $request->user(),
                (string) $validated['approved_amount'],
                isset($validated['daily_limit_myr']) ? (string) $validated['daily_limit_myr'] : null
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation approved.');
    }

    /**
     * Reject a pending allocation request (manager/admin, own branch).
     */
    public function reject(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        $this->authorizeAllocationBranch($request, $allocation);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $allocation->isPending()) {
            return back()->with('error', 'Allocation is not pending approval.');
        }

        try {
            $this->allocationService->rejectAllocation(
                $allocation,
                $request->user(),
                $validated['rejection_reason'] ?? null
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation rejected.');
    }

    /**
     * Manager-initiated allocation form: hand stock from the branch pool
     * to a teller without waiting for a request.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $branch = $user->branch;

        $tellers = User::where('is_active', true)
            ->where('role', UserRole::Teller->value)
            ->when(! $user->role->canManageAllBranches() && $branch,
                fn ($q) => $q->where('branch_id', $branch->id))
            ->orderBy('username')
            ->get();

        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        $branchIds = $tellers->pluck('branch_id')->filter()->unique()->values();
        $poolSummary = $this->poolSummary($branchIds);

        // {"branchId:CCY": available} — lets the form hint availability for
        // whichever branch the selected teller belongs to.
        $poolAvailable = BranchPool::whereIn('branch_id', $branchIds)->get()
            ->mapWithKeys(fn (BranchPool $p) => [$p->branch_id.':'.$p->currency_code => (float) $p->available_balance]);

        $tellerBranches = $tellers->mapWithKeys(fn (User $t) => [$t->id => $t->branch_id]);

        return view('allocations.create', compact('tellers', 'currencies', 'poolSummary', 'poolAvailable', 'tellerBranches'));
    }

    /**
     * Manager-initiated allocation: create the request and approve it in one
     * step. Status lands on APPROVED so the teller still acknowledges custody
     * via Accept on My Allocations — same as a request-driven approval.
     * Accepts multiple currency lines; the batch is atomic.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code', 'distinct'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.0001'],
            'daily_limit_myr' => ['nullable', 'numeric', 'min:0'],
        ]);

        $teller = User::query()->find((int) $validated['user_id']);

        abort_unless(
            $teller?->isTeller()
                && ($request->user()->role->canManageAllBranches()
                    || (int) $teller->branch_id === (int) $request->user()->branch_id),
            403
        );

        $dailyLimit = isset($validated['daily_limit_myr']) ? (string) $validated['daily_limit_myr'] : null;

        $created = collect();

        try {
            DB::transaction(function () use ($validated, $teller, $request, $dailyLimit, $created) {
                foreach ($validated['lines'] as $line) {
                    $allocation = $this->allocationService->requestAllocation(
                        $teller,
                        $request->user(),
                        $line['currency_code'],
                        (string) $line['amount'],
                        $dailyLimit
                    );

                    $this->allocationService->approveAllocation(
                        $allocation,
                        $request->user(),
                        (string) $line['amount'],
                        $dailyLimit
                    );

                    $created->push($allocation);
                }
            });
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($created->count() === 1) {
            return redirect()
                ->route('allocations.show', $created->first()->id)
                ->with('success', 'Allocation created and approved — awaiting teller acceptance.');
        }

        return redirect()
            ->route('allocations.index')
            ->with('success', $created->count().' allocations created and approved — awaiting teller acceptance.');
    }

    /**
     * Increase or decrease an approved/active allocation (manager/admin).
     * Increase draws more from the branch pool; decrease returns unspent
     * float to the pool — see TellerAllocationService::modifyAllocation().
     */
    public function modify(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        $this->authorizeAllocationBranch($request, $allocation);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.0001'],
            'direction' => ['required', 'in:increase,decrease'],
        ]);

        if (! $allocation->isApproved() && ! $allocation->isActive()) {
            return back()->with('error', 'Only approved or active allocations can be adjusted.');
        }

        try {
            $this->allocationService->modifyAllocation(
                $allocation,
                $request->user(),
                (string) $validated['amount'],
                $validated['direction'] === 'increase'
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Allocation {$validated['direction']}d by {$validated['amount']}.");
    }

    /**
     * Return an active allocation to the branch pool (manager/admin, own branch).
     */
    public function returnToPool(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        $this->authorizeAllocationBranch($request, $allocation);

        if (! $allocation->isActive()) {
            return back()->with('error', 'Allocation is not active.');
        }

        try {
            $this->allocationService->returnToPool($allocation);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation returned to the branch pool.');
    }

    /**
     * List the authenticated teller's own allocations.
     */
    public function myIndex(Request $request): View
    {
        $allocations = TellerAllocation::with(['currency', 'approver'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(25);

        // Expected drawer contents for the teller's open session — same
        // formula the close workflow uses (MYR: opening + transaction_total;
        // FCY: opening + buys − sells via expectedClosingForBalance).
        $session = CounterSession::open()
            ->where('user_id', $request->user()->id)
            ->with('counter')
            ->latest('opened_at')
            ->first();

        $till = $session
            ? TillBalance::where('till_id', $session->tillCode())
                ->whereDate('date', $session->session_date)
                ->whereNull('closed_at')
                ->orderBy('currency_code')
                ->get()
                ->keyBy('currency_code')
                ->map(fn (TillBalance $b) => $this->tillService->expectedClosingForBalance($b))
            : collect();

        $poolSummary = $this->poolSummary(collect([$request->user()->branch_id])->filter());

        return view('allocations.my-index', compact('allocations', 'session', 'till', 'poolSummary'));
    }

    /**
     * Stock request form for the authenticated teller.
     */
    public function requestForm(Request $request): View
    {
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();
        $counters = Counter::query()
            ->where('branch_id', $request->user()->branch_id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('allocations.request', compact('currencies', 'counters'));
    }

    /**
     * Submit a teller stock request. Creates a pending allocation that the
     * branch manager approves from the allocations screen.
     */
    public function submitRequest(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'requested_amount' => ['required', 'numeric', 'min:0.0001'],
            'counter_id' => ['nullable', 'integer', 'exists:counters,id'],
        ]);

        $counter = isset($validated['counter_id'])
            ? Counter::query()->find((int) $validated['counter_id'])
            : null;

        try {
            $this->allocationService->requestAllocation(
                $user,
                $user,
                $validated['currency_code'],
                (string) $validated['requested_amount'],
                null,
                $counter
            );
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('my-allocations.index')
            ->with('success', 'Stock request submitted. Awaiting manager approval.');
    }

    /**
     * Teller accepts an approved assignment, activating the allocation.
     */
    public function accept(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->user_id === $request->user()->id, 403);

        if (! $allocation->isApproved()) {
            return back()->with('error', 'Allocation is not awaiting acceptance.');
        }

        try {
            $this->allocationService->activateAllocation($allocation);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation activated.');
    }

    /**
     * Teller returns an active allocation to the branch pool.
     */
    public function requestReturn(Request $request, TellerAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->user_id === $request->user()->id, 403);

        if (! $allocation->isActive()) {
            return back()->with('error', 'Allocation is not active.');
        }

        try {
            $this->allocationService->returnToPool($allocation);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation returned to the branch pool.');
    }

    /**
     * Per-branch pool breakdown: total pool, available to allocate, and the
     * amount currently allocated to each teller (approved + active).
     *
     * @param  iterable<int>  $branchIds
     * @return array<int, array{branch: string, rows: array<int, array{currency: string, total: float, available: float, tellers: array<int, array{name: string, amount: float}>}>}>
     */
    private function poolSummary(iterable $branchIds): array
    {
        $branchIds = collect($branchIds);

        if ($branchIds->isEmpty()) {
            return [];
        }

        $branches = Branch::whereIn('id', $branchIds)->get()->keyBy('id');
        $pools = BranchPool::whereIn('branch_id', $branchIds)->get();
        $allocations = TellerAllocation::whereIn('branch_id', $branchIds)
            ->whereIn('status', [TellerAllocationStatus::APPROVED, TellerAllocationStatus::ACTIVE])
            ->with('user:id,username')
            ->get(['id', 'branch_id', 'user_id', 'currency_code', 'allocated_amount']);

        $summary = [];

        foreach ($pools->groupBy('branch_id') as $branchId => $branchPools) {
            $rows = $branchPools->map(function (BranchPool $pool) use ($allocations, $branchId) {
                $tellers = $allocations
                    ->where('branch_id', $branchId)
                    ->where('currency_code', $pool->currency_code)
                    ->groupBy('user_id')
                    ->map(fn (Collection $group) => [
                        'name' => $group->first()->user->username ?? 'unknown',
                        'amount' => (float) $group->sum('allocated_amount'),
                    ])
                    ->sortBy('name')
                    ->values()
                    ->all();

                return [
                    'currency' => $pool->currency_code,
                    'total' => (float) $pool->available_balance + (float) $pool->allocated_balance,
                    'available' => (float) $pool->available_balance,
                    'tellers' => $tellers,
                ];
            })->sortBy('currency')->values()->all();

            $summary[] = [
                'branch' => $branches->get((int) $branchId)->name ?? "Branch {$branchId}",
                'rows' => $rows,
            ];
        }

        return $summary;
    }

    /**
     * Non-admin users may only act on allocations in their own branch.
     */
    private function authorizeAllocationBranch(Request $request, TellerAllocation $allocation): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || (int) $allocation->branch_id === (int) $user->branch_id,
            403
        );
    }
}
