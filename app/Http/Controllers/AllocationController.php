<?php

namespace App\Http\Controllers;

use App\Enums\TellerAllocationStatus;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Services\Branch\TellerAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllocationController extends Controller
{
    public function __construct(
        protected TellerAllocationService $allocationService,
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
        $allocations = TellerAllocation::with(['counter', 'currency', 'approver'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(25);

        return view('allocations.my-index', compact('allocations'));
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
