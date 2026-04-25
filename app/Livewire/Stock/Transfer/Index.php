<?php

namespace App\Livewire\Stock\Transfer;

use App\Livewire\BaseComponent;
use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class Index extends BaseComponent
{
    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public array $transfers = [];

    public int $totalCount = 0;

    public array $statusCounts = [];

    protected StockTransfer $stockTransferModel;

    public function mount(): void
    {
        $this->stockTransferModel = new StockTransfer;
        $this->loadTransfers();
    }

    public function loadTransfers(): void
    {
        $query = StockTransfer::with(['items', 'requestedBy']);

        // Apply status filter
        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        // Apply date range filter
        if (! empty($this->dateFrom)) {
            $query->whereDate('transfer_date', '>=', $this->dateFrom);
        }

        if (! empty($this->dateTo)) {
            $query->whereDate('transfer_date', '<=', $this->dateTo);
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        /** @var LengthAwarePaginator $paginatedTransfers */
        $paginatedTransfers = $query->paginate(25);

        $this->transfers = $paginatedTransfers->items();
        $this->totalCount = $paginatedTransfers->total();

        // Calculate status counts
        $this->statusCounts = [
            'all' => StockTransfer::count(),
            'requested' => StockTransfer::where('status', StockTransfer::STATUS_REQUESTED)->count(),
            'bm_approved' => StockTransfer::where('status', StockTransfer::STATUS_BM_APPROVED)->count(),
            'hq_approved' => StockTransfer::where('status', StockTransfer::STATUS_HQ_APPROVED)->count(),
            'in_transit' => StockTransfer::where('status', StockTransfer::STATUS_IN_TRANSIT)->count(),
            'completed' => StockTransfer::where('status', StockTransfer::STATUS_COMPLETED)->count(),
            'cancelled' => StockTransfer::where('status', StockTransfer::STATUS_CANCELLED)->count(),
        ];
    }

    public function updatedStatusFilter(): void
    {
        $this->loadTransfers();
    }

    public function updatedDateFrom(): void
    {
        $this->loadTransfers();
    }

    public function updatedDateTo(): void
    {
        $this->loadTransfers();
    }

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->loadTransfers();
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

    public function paginationView(): string
    {
        return 'vendor.livewire.tailwind';
    }

    public function render()
    {
        return view('livewire.stock.transfer.index');
    }
}
