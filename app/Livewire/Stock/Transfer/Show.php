<?php

namespace App\Livewire\Stock\Transfer;

use App\Enums\UserRole;
use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use App\Services\AuditService;
use App\Services\MathService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;

class Show extends BaseComponent
{
    public StockTransfer $stockTransfer;

    public int $stockTransferId;

    public array $transferData = [];

    public array $items = [];

    public string $totalValue = '0.00';

    public bool $canApproveBm = false;

    public bool $canApproveHq = false;

    public bool $canDispatch = false;

    public bool $canReceive = false;

    public bool $canComplete = false;

    public bool $canCancel = false;

    public bool $canView = false;

    public string $cancelReason = '';

    public bool $showCancelModal = false;

    public bool $showReceiveModal = false;

    public array $receiveQuantities = [];

    protected MathService $mathService;

    protected AuditService $auditService;

    public function mount(int $stockTransferId): void
    {
        $this->stockTransferId = $stockTransferId;
        $this->mathService = new MathService;
        $this->auditService = new AuditService;
        $this->loadTransfer();
    }

    public function loadTransfer(): void
    {
        $this->stockTransfer = StockTransfer::with([
            'items',
            'requestedBy',
            'branchManagerApprovedBy',
            'hqApprovedBy',
        ])->findOrFail($this->stockTransferId);

        $this->prepareTransferData();
        $this->checkPermissions();
    }

    protected function prepareTransferData(): void
    {
        $this->transferData = [
            'id' => $this->stockTransfer->id,
            'transfer_number' => $this->stockTransfer->transfer_number,
            'transfer_date' => $this->stockTransfer->transfer_date,
            'type' => $this->stockTransfer->type,
            'status' => $this->stockTransfer->status,
            'status_label' => $this->getStatusLabel($this->stockTransfer->status),
            'status_badge_class' => $this->getStatusBadgeClass($this->stockTransfer->status),
            'source_branch_name' => $this->stockTransfer->source_branch_name,
            'destination_branch_name' => $this->stockTransfer->destination_branch_name,
            'notes' => $this->stockTransfer->notes,
            'cancellation_reason' => $this->stockTransfer->cancellation_reason,
            'total_value' => $this->stockTransfer->total_value_myr ?? '0',
            'requested_by' => $this->stockTransfer->requestedBy?->name ?? 'N/A',
            'requested_at' => $this->stockTransfer->requested_at?->format('d M Y H:i') ?? 'N/A',
            'branch_manager_approved_by' => $this->stockTransfer->branchManagerApprovedBy?->name ?? null,
            'branch_manager_approved_at' => $this->stockTransfer->branch_manager_approved_at?->format('d M Y H:i') ?? null,
            'hq_approved_by' => $this->stockTransfer->hqApprovedBy?->name ?? null,
            'hq_approved_at' => $this->stockTransfer->hq_approved_at?->format('d M Y H:i') ?? null,
            'dispatched_at' => $this->stockTransfer->dispatched_at?->format('d M Y H:i') ?? null,
            'completed_at' => $this->stockTransfer->completed_at?->format('d M Y H:i') ?? null,
        ];

        $this->totalValue = $this->stockTransfer->total_value_myr ?? '0';

        $this->items = $this->stockTransfer->items->map(function ($item) {
            return [
                'id' => $item->id,
                'currency_code' => $item->currency_code,
                'quantity' => $item->quantity,
                'rate' => $item->rate,
                'value_myr' => $item->value_myr,
                'quantity_received' => $item->quantity_received ?? '0',
                'quantity_in_transit' => $item->quantity_in_transit ?? $item->quantity,
                'variance' => $item->variance,
                'has_variance' => $item->hasVariance(),
                'is_fully_received' => $item->isFullyReceived(),
            ];
        })->toArray();

        // Initialize receive quantities for receive modal
        foreach ($this->items as $item) {
            $this->receiveQuantities[$item['id']] = $item['quantity'];
        }
    }

    protected function checkPermissions(): void
    {
        $user = Auth::user();
        $role = $user->role;

        $this->canView = true;

        // Can approve by BM if status is REQUESTED and user is Manager or Admin
        $this->canApproveBm = $this->stockTransfer->status === StockTransfer::STATUS_REQUESTED
            && ($role === UserRole::Manager || $role === UserRole::Admin);

        // Can approve by HQ if status is BM_APPROVED and user is Admin
        $this->canApproveHq = $this->stockTransfer->status === StockTransfer::STATUS_BM_APPROVED
            && $role === UserRole::Admin;

        // Can dispatch if status is HQ_APPROVED and user is Admin
        $this->canDispatch = $this->stockTransfer->status === StockTransfer::STATUS_HQ_APPROVED
            && $role === UserRole::Admin;

        // Can receive if status is IN_TRANSIT and user is Admin
        $this->canReceive = $this->stockTransfer->status === StockTransfer::STATUS_IN_TRANSIT
            && $role === UserRole::Admin;

        // Can complete if status is IN_TRANSIT or PARTIALLY_RECEIVED and user is Admin
        $this->canComplete = in_array($this->stockTransfer->status, [
            StockTransfer::STATUS_IN_TRANSIT,
            StockTransfer::STATUS_PARTIALLY_RECEIVED,
        ]) && $role === UserRole::Admin;

        // Can cancel if not completed/cancelled and user is Manager or Admin
        $this->canCancel = ! in_array($this->stockTransfer->status, [
            StockTransfer::STATUS_COMPLETED,
            StockTransfer::STATUS_CANCELLED,
        ]) && ($role === UserRole::Manager || $role === UserRole::Admin);
    }

    public function approveByBranchManager(): void
    {
        try {
            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->approveByBranchManager($this->stockTransfer);

            $this->auditService->logStockTransferEvent('stock_transfer_approved_bm', $this->stockTransfer->id, [
                'new' => ['approved_by' => Auth::user()->username],
            ]);

            $this->success('Transfer approved by branch manager');
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function approveByHQ(): void
    {
        try {
            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->approveByHQ($this->stockTransfer);

            $this->auditService->logStockTransferEvent('stock_transfer_approved_hq', $this->stockTransfer->id, [
                'new' => ['approved_by' => Auth::user()->username],
            ]);

            $this->success('Transfer approved by HQ');
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function dispatchTransfer(): void
    {
        try {
            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->dispatch($this->stockTransfer);

            $this->auditService->logStockTransferEvent('stock_transfer_dispatched', $this->stockTransfer->id);

            $this->success('Transfer dispatched');
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function openReceiveModal(): void
    {
        $this->showReceiveModal = true;
    }

    public function closeReceiveModal(): void
    {
        $this->showReceiveModal = false;
    }

    public function receive(): void
    {
        try {
            $items = [];
            foreach ($this->receiveQuantities as $itemId => $quantityReceived) {
                $items[] = [
                    'id' => $itemId,
                    'quantity_received' => $quantityReceived,
                ];
            }

            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->receiveItems($this->stockTransfer, $items);

            $this->auditService->logStockTransferEvent('stock_transfer_partially_received', $this->stockTransfer->id, [
                'new' => ['received_items' => $items],
            ]);

            $this->success('Items received');
            $this->showReceiveModal = false;
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function complete(): void
    {
        try {
            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->complete($this->stockTransfer);

            $this->auditService->logStockTransferEvent('stock_transfer_completed', $this->stockTransfer->id);

            $this->success('Transfer completed');
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function openCancelModal(): void
    {
        $this->showCancelModal = true;
        $this->cancelReason = '';
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancelReason = '';
    }

    public function cancel(): void
    {
        if (empty($this->cancelReason)) {
            $this->error('Cancellation reason is required');

            return;
        }

        try {
            $stockTransferService = new StockTransferService($this->mathService, $this->auditService, Auth::user());
            $stockTransferService->cancel($this->stockTransfer, $this->cancelReason);

            $this->auditService->logStockTransferEvent('stock_transfer_cancelled', $this->stockTransfer->id, [
                'new' => [
                    'reason' => $this->cancelReason,
                    'cancelled_by' => Auth::user()->username,
                ],
            ]);

            $this->success('Transfer cancelled');
            $this->showCancelModal = false;
            $this->loadTransfer();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
        }
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            StockTransfer::STATUS_REQUESTED => 'badge-warning',
            StockTransfer::STATUS_BM_APPROVED => 'badge-info',
            StockTransfer::STATUS_HQ_APPROVED => 'badge-info',
            StockTransfer::STATUS_IN_TRANSIT => 'badge-accent',
            StockTransfer::STATUS_PARTIALLY_RECEIVED => 'badge-warning',
            StockTransfer::STATUS_COMPLETED => 'badge-success',
            StockTransfer::STATUS_CANCELLED => 'badge-danger',
            StockTransfer::STATUS_REJECTED => 'badge-danger',
            default => 'badge-default',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            StockTransfer::STATUS_REQUESTED => 'Pending',
            StockTransfer::STATUS_BM_APPROVED => 'BM Approved',
            StockTransfer::STATUS_HQ_APPROVED => 'HQ Approved',
            StockTransfer::STATUS_IN_TRANSIT => 'In Transit',
            StockTransfer::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            StockTransfer::STATUS_COMPLETED => 'Completed',
            StockTransfer::STATUS_CANCELLED => 'Cancelled',
            StockTransfer::STATUS_REJECTED => 'Rejected',
            default => $status,
        };
    }

    public function back(): void
    {
        $this->redirect(route('stock-transfers.index'));
    }

    public function render()
    {
        return view('livewire.stock.transfer.show');
    }
}
