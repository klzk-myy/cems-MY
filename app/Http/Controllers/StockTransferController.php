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
use App\Policies\StockTransferPolicy;
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

        // Branch scoping (consistent with StockTransferPolicy): admins see every
        // branch, everyone else only sees transfers touching their own branch
        // (as source or destination).
        if (! $user?->isAdmin()) {
            $identifiers = StockTransferPolicy::branchIdentifiers($user);

            if ($identifiers === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($identifiers) {
                    $q->whereIn('source_branch_name', $identifiers)
                        ->orWhereIn('destination_branch_name', $identifiers);
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

        // Transfers store branch names (schema contract), so options are
        // keyed by name. Maker rule: a non-admin manager may only source
        // stock from their own branch.
        $user = auth()->user();
        $branches = Branch::orderBy('name')->pluck('name', 'name');
        $sourceBranches = $user->isAdmin()
            ? $branches
            : $branches->only([$user->branch?->name, $user->branch?->code])->filter();
        // Currency's primary key is its ISO code, so options are keyed by code.
        $currencies = Currency::where('is_active', true)->orderBy('name')->pluck('name', 'code');

        return view('stock-transfers.create', compact('branches', 'sourceBranches', 'currencies'));
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
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load(['items', 'requestedBy', 'branchManagerApprovedBy', 'hqApprovedBy']);

        return view('stock-transfers.show', compact('stockTransfer'));
    }

    public function showStep(StockTransfer $stockTransfer, string $step): View
    {
        $this->requireManagerOrAdmin();
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load(['items', 'requestedBy', 'branchManagerApprovedBy', 'hqApprovedBy']);

        return view('stock-transfers.show', compact('stockTransfer', 'step'));
    }

    public function approveBm(ApproveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->authorize('approveBranchManager', $stockTransfer);

        $this->stockTransferService->approveByBranchManager($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_bm', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by branch manager');
    }

    public function approveHq(ApproveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('approveHq', $stockTransfer);

        $this->stockTransferService->approveByHQ($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_hq', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by HQ');
    }

    public function dispatch(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->authorize('dispatch', $stockTransfer);

        $this->stockTransferService->dispatch($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_dispatched', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer dispatched');
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->authorize('receive', $stockTransfer);

        $this->stockTransferService->receiveItems($stockTransfer, $request->items);

        $this->auditService->logStockTransferEvent('stock_transfer_partially_received', $stockTransfer->id, [
            'new' => ['received_items' => $request->items],
        ]);

        return redirect()->back()->with('success', 'Items received');
    }

    public function complete(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->authorize('complete', $stockTransfer);

        $this->stockTransferService->complete($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_completed', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer completed');
    }

    public function cancel(CancelStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->authorize('cancel', $stockTransfer);

        $this->stockTransferService->cancel($stockTransfer, $request->reason);

        $this->auditService->logStockTransferEvent('stock_transfer_cancelled', $stockTransfer->id, [
            'new' => ['reason' => $request->reason, 'cancelled_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer cancelled');
    }

    public function reject(CancelStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('reject', $stockTransfer);

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
}
