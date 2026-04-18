<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Services\AuditService;
use App\Services\StockTransferService;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    protected function getStockTransferService(): StockTransferService
    {
        return new StockTransferService(auth()->user());
    }

    public function index(Request $request)
    {
        $query = StockTransfer::with(['items', 'requestedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source_branch')) {
            $query->where('source_branch_name', $request->source_branch);
        }

        if ($request->has('destination_branch')) {
            $query->where('destination_branch_name', $request->destination_branch);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('stock-transfers.index', compact('transfers'));
    }

    public function create()
    {
        return view('stock-transfers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_branch_name' => 'required|string',
            'destination_branch_name' => 'required|string|different:source_branch_name',
            'type' => 'required|in:Standard,Emergency,Scheduled,Return',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.currency_code' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.value_myr' => 'required|numeric|min:0',
        ]);

        $transfer = $this->getStockTransferService()->createRequest($validated);

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

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items', 'requestedBy', 'branchManagerApprovedBy', 'hqApprovedBy']);

        return view('stock-transfers.show', compact('stockTransfer'));
    }

    public function approveBm(StockTransfer $stockTransfer)
    {
        $this->getStockTransferService()->approveByBranchManager($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_bm', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by branch manager');
    }

    public function approveHq(StockTransfer $stockTransfer)
    {
        $this->getStockTransferService()->approveByHQ($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_approved_hq', $stockTransfer->id, [
            'new' => ['approved_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer approved by HQ');
    }

    public function dispatch(StockTransfer $stockTransfer)
    {
        $this->getStockTransferService()->dispatch($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_dispatched', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer dispatched');
    }

    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ]);

        $this->getStockTransferService()->receiveItems($stockTransfer, $request->items);

        $this->auditService->logStockTransferEvent('stock_transfer_partially_received', $stockTransfer->id, [
            'new' => ['received_items' => $request->items],
        ]);

        return redirect()->back()->with('success', 'Items received');
    }

    public function complete(StockTransfer $stockTransfer)
    {
        $this->getStockTransferService()->complete($stockTransfer);

        $this->auditService->logStockTransferEvent('stock_transfer_completed', $stockTransfer->id);

        return redirect()->back()->with('success', 'Transfer completed');
    }

    public function cancel(Request $request, StockTransfer $stockTransfer)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->getStockTransferService()->cancel($stockTransfer, $request->reason);

        $this->auditService->logStockTransferEvent('stock_transfer_cancelled', $stockTransfer->id, [
            'new' => ['reason' => $request->reason, 'cancelled_by' => auth()->user()->username],
        ]);

        return redirect()->back()->with('success', 'Transfer cancelled');
    }
}
