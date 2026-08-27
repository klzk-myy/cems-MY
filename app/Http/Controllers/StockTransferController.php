<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\TransactionApprovalException;
use App\Http\Requests\ApproveStockTransferRequest;
use App\Http\Requests\CancelStockTransferRequest;
use App\Http\Requests\ReceiveStockTransferRequest;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\StockTransfer;
use App\Services\AuditService;
use App\Services\Transaction\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected StockTransferService $stockTransferService,
    ) {}

    public function index(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $user = auth()->user();
        $query = StockTransfer::with(['items', 'requestedBy']);

        // Branch scoping: admins see every branch, everyone else only sees
        // transfers touching their own branch (as source or destination).
        if (! $user?->isAdmin()) {
            if (! $user->branch_id) {
                $query->whereRaw('1 = 0');
            } else {
                $branchNames = $this->currentUserBranchIdentifiers();

                $query->where(function ($q) use ($branchNames) {
                    $q->whereIn('source_branch_name', $branchNames)
                        ->orWhereIn('destination_branch_name', $branchNames);
                });
            }
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source_branch')) {
            $query->where('source_branch_name', $request->source_branch);
        }

        if ($request->has('destination_branch')) {
            $query->where('destination_branch_name', $request->destination_branch);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        return view('stock-transfers.index', compact('transfers'));
    }

    public function create(): View
    {
        $this->requireManagerOrAdmin();

        $branches = Branch::orderBy('name')->pluck('name', 'id');
        // Currency's primary key is its ISO code, so options are keyed by code.
        $currencies = Currency::where('is_active', true)->orderBy('name')->pluck('name', 'code');

        return view('stock-transfers.create', compact('branches', 'currencies'));
    }

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $this->requireManagerOrAdmin();

        $validated = $request->validated();

        $transfer = $this->stockTransferService->createRequest($validated);

        $this->auditService->logStockTransferEvent('stock_transfer_created', $transfer->id, [
            'new' => [
                'transfer_number' => $transfer->transfer_number,
                'source_branch' => $transfer->source_branch_name,
                'destination_branch' => $transfer->destination_branch_name,
                'type' => $transfer->type,
            ],
        ]);

        return redirect()->route('stock-transfers.show', $transfer->id)
            ->with('success', 'Transfer request created');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->requireManagerOrAdmin();
        $this->ensureInvolvedInTransfer($stockTransfer);

        $stockTransfer->load(['items', 'requestedBy', 'branchManagerApprovedBy', 'hqApprovedBy']);

        return view('stock-transfers.show', compact('stockTransfer'));
    }

    public function showStep(StockTransfer $stockTransfer, string $step): View
    {
        $this->requireManagerOrAdmin();
        $this->ensureInvolvedInTransfer($stockTransfer);

        $stockTransfer->load(['items', 'requestedBy', 'branchManagerApprovedBy', 'hqApprovedBy']);

        return view('stock-transfers.show', compact('stockTransfer', 'step'));
    }

    public function approveBm(ApproveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureSourceBranchManager($stockTransfer);

        $this->stockTransferService->approveByBranchManager($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_bm', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by branch manager');
    }

    public function approveHq(ApproveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireAdmin();

        $this->stockTransferService->approveByHQ($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_hq', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by HQ');
    }

    public function dispatch(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureSourceBranchManager($stockTransfer);

        $this->stockTransferService->dispatch($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_dispatched', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer dispatched');
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureDestinationMember($stockTransfer);

        $this->stockTransferService->receiveItems($stockTransfer, $request->items);

        $this->auditService->logStockTransferEvent('stock_transfer_partially_received', $stockTransfer->id, [
            'new' => ['received_items' => $request->items],
        ]);

        return redirect()->back()->with('success', 'Items received');
    }

    public function complete(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureDestinationMember($stockTransfer);

        $this->stockTransferService->complete($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_completed', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer completed');
    }

    public function cancel(CancelStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureSourceBranchManager($stockTransfer);

        $this->stockTransferService->cancel($stockTransfer, $request->reason);

        $this->auditService->logStockTransferEvent('stock_transfer_cancelled', $stockTransfer->id, [
            'new' => ['reason' => $request->reason, 'cancelled_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer cancelled');
    }

    public function reject(CancelStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireAdmin();

        try {
            $this->stockTransferService->reject($stockTransfer, $request->reason);
        } catch (TransactionApprovalException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditService->logStockTransferEvent('stock_transfer_rejected', $stockTransfer->id, [
            'new' => ['reason' => $request->reason, 'rejected_by' => auth()->user()?->username],
        ]);

        return redirect()->back()->with('success', 'Transfer rejected');
    }

    /**
     * Resolve a free-form branch identifier (name or code, as stored on the
     * transfer) to a branches.id. Returns null when unresolvable - callers
     * deny access in that case (fail-closed).
     */
    private function resolveBranchId(?string $identifier): ?int
    {
        if ($identifier === null || trim($identifier) === '') {
            return null;
        }

        return Branch::query()
            ->where('name', $identifier)
            ->orWhere('code', $identifier)
            ->value('id');
    }

    /**
     * Name and code identifiers of the authenticated user's branch.
     *
     * @return array<int, string>
     */
    private function currentUserBranchIdentifiers(): array
    {
        return Branch::query()
            ->whereKey(auth()->user()?->branch_id)
            ->get(['name', 'code'])
            ->flatMap(fn (Branch $branch) => array_filter([$branch->name, $branch->code]))
            ->values()
            ->all();
    }

    /**
     * Admins act across all branches; managers must be assigned to the
     * transfer's SOURCE branch (approve-bm / dispatch / cancel).
     */
    private function ensureSourceBranchManager(StockTransfer $stockTransfer): void
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return;
        }

        if ((int) $this->resolveBranchId($stockTransfer->source_branch_name) !== (int) $user->branch_id) {
            abort(403, 'You can only manage stock transfers from your own branch.');
        }
    }

    /**
     * Admins act across all branches; receiving/completing requires membership
     * of the transfer's DESTINATION branch.
     */
    private function ensureDestinationMember(StockTransfer $stockTransfer): void
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return;
        }

        if ((int) $this->resolveBranchId($stockTransfer->destination_branch_name) !== (int) $user->branch_id) {
            abort(403, 'You can only receive stock transfers destined for your own branch.');
        }
    }

    /**
     * Viewing requires involvement: admins see everything, other users only
     * transfers where their branch is the source or the destination.
     */
    private function ensureInvolvedInTransfer(StockTransfer $stockTransfer): void
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return;
        }

        $branchId = (int) $user->branch_id;

        if ((int) $this->resolveBranchId($stockTransfer->source_branch_name) !== $branchId
            && (int) $this->resolveBranchId($stockTransfer->destination_branch_name) !== $branchId) {
            abort(403, 'You can only view stock transfers involving your own branch.');
        }
    }
}
