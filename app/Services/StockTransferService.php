<?php

namespace App\Services;

use App\Enums\StockTransferStatus;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    protected User $requester;

    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        ?User $requester = null,
    ) {
        $this->requester = $requester ?? auth()->user();
    }

    public function createRequest(array $data): StockTransfer
    {
        // Validate business rules
        if (empty($data['source_branch_name']) || empty($data['destination_branch_name'])) {
            throw new \InvalidArgumentException('Source and destination branches are required');
        }

        if ($data['source_branch_name'] === $data['destination_branch_name']) {
            throw new \InvalidArgumentException('Source and destination branches cannot be the same');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw new \InvalidArgumentException('At least one item is required');
        }

        // Validate each item
        foreach ($data['items'] as $item) {
            if (empty($item['currency_code'])) {
                throw new \InvalidArgumentException('Currency code is required for each item');
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException('Quantity must be a positive number');
            }

            if (! isset($item['rate']) || $item['rate'] <= 0) {
                throw new \InvalidArgumentException('Rate must be a positive number');
            }

            // Verify currency exists
            if (! Currency::where('code', $item['currency_code'])->exists()) {
                throw new \InvalidArgumentException("Currency {$item['currency_code']} does not exist");
            }
        }

        // Calculate and validate total value
        $calculatedTotal = '0';
        foreach ($data['items'] as $item) {
            $itemValue = $this->mathService->multiply($item['quantity'], $item['rate']);
            $calculatedTotal = $this->mathService->add($calculatedTotal, $itemValue);
        }

        if (isset($data['total_value_myr']) && $this->mathService->compare($data['total_value_myr'], $calculatedTotal) !== 0) {
            throw new \InvalidArgumentException('Total value does not match sum of item values');
        }

        return DB::transaction(function () use ($data, $calculatedTotal) {
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'type' => $data['type'] ?? StockTransfer::TYPE_STANDARD,
                'status' => StockTransferStatus::Requested->value,
                'source_branch_name' => $data['source_branch_name'],
                'destination_branch_name' => $data['destination_branch_name'],
                'requested_by' => $this->requester->id,
                'requested_at' => now(),
                'notes' => $data['notes'] ?? null,
                'total_value_myr' => $calculatedTotal,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'currency_code' => $item['currency_code'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'value_myr' => $this->mathService->multiply($item['quantity'], $item['rate']),
                ]);
            }

            return $transfer->load('items');
        });
    }

    public function approveByBranchManager(StockTransfer $transfer): void
    {
        if ($this->requester->role !== UserRole::Manager && $this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only managers can approve transfers');
        }

        if (! $transfer->isPending()) {
            throw new \RuntimeException('Transfer is not in requested status');
        }

        $transfer->approveByBranchManager($this->requester);
    }

    public function approveByHQ(StockTransfer $transfer): void
    {
        if ($this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only HQ (Admin) can approve transfers');
        }

        if ($transfer->status !== StockTransferStatus::BranchManagerApproved->value) {
            throw new \RuntimeException('Transfer must be BM-approved before HQ approval');
        }

        $transfer->approveByHQ($this->requester);
    }

    public function dispatch(StockTransfer $transfer): void
    {
        if ($this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only admin can dispatch transfers');
        }

        if ($transfer->status !== StockTransferStatus::HqApproved->value) {
            throw new \RuntimeException('Transfer must be HQ-approved before dispatch');
        }

        $transfer->dispatch();
    }

    public function receiveItems(StockTransfer $transfer, array $items): void
    {
        if ($this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only admin can receive items');
        }

        if ($transfer->status !== StockTransferStatus::InTransit->value) {
            throw new \RuntimeException('Transfer must be in transit to receive items');
        }

        DB::transaction(function () use ($transfer, $items) {
            foreach ($items as $itemData) {
                $item = $transfer->items()->where('id', $itemData['id'])->first();
                if ($item) {
                    $item->update([
                        'quantity_received' => $itemData['quantity_received'],
                        'quantity_in_transit' => $this->mathService->subtract($item->quantity, $itemData['quantity_received']),
                    ]);

                    if ($item->hasVariance()) {
                        $item->update(['variance_notes' => "Variance: {$item->variance}"]);

                        if ($this->mathService->compare($item->quantity, '0') > 0) {
                            $variancePercent = $this->mathService->multiply(
                                $this->mathService->divide(abs($item->variance), $item->quantity),
                                '100'
                            );
                            if ($this->mathService->compare($variancePercent, '5') > 0) {
                                $this->auditService->logWithSeverity(
                                    'stock_transfer_variance_exceeded',
                                    [
                                        'entity_type' => 'StockTransfer',
                                        'entity_id' => $transfer->id,
                                        'new_values' => [
                                            'item_id' => $item->id,
                                            'currency' => $item->currency_code,
                                            'variance_percent' => $variancePercent,
                                        ],
                                    ],
                                    'WARNING'
                                );
                            }
                        }
                    }
                }
            }

            $allFullyReceived = $transfer->items->every(fn ($item) => $item->isFullyReceived());
            if (! $allFullyReceived) {
                $transfer->update(['status' => StockTransferStatus::PartiallyReceived->value]);
            }
        });
    }

    public function complete(StockTransfer $transfer): void
    {
        if ($this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only admin can complete transfers');
        }

        if (! in_array($transfer->status, [StockTransferStatus::InTransit->value, StockTransferStatus::PartiallyReceived->value])) {
            throw new \RuntimeException('Transfer must be in transit or partially received to complete');
        }

        $transfer->complete();
    }

    public function cancel(StockTransfer $transfer, string $reason): void
    {
        if ($this->requester->role !== UserRole::Manager && $this->requester->role !== UserRole::Admin) {
            throw new \RuntimeException('Only managers can cancel transfers');
        }

        if ($transfer->isCompleted()) {
            throw new \RuntimeException('Cannot cancel a completed transfer');
        }

        if ($transfer->status === StockTransferStatus::Cancelled->value) {
            throw new \RuntimeException('Transfer is already cancelled');
        }

        $transfer->cancel($reason);
    }

    public function getPendingTransfers(): Collection
    {
        return StockTransfer::pending()->with('items')->get();
    }

    public function getInTransitTransfers(): Collection
    {
        return StockTransfer::inTransit()->with('items')->get();
    }

    public function getTransfersByBranch(string $branchName): Collection
    {
        return StockTransfer::where('source_branch_name', $branchName)
            ->orWhere('destination_branch_name', $branchName)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
