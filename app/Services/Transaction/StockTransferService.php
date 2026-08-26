<?php

namespace App\Services\Transaction;

use App\Enums\StockTransferStatus;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TransactionApprovalException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    protected ?User $requester = null;

    protected ?CurrencyPositionLockService $positionLockService;

    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        ?User $requester = null,
        ?CurrencyPositionLockService $positionLockService = null,
    ) {
        $this->requester = $requester ?? auth()->user();
        $this->positionLockService = $positionLockService;
    }

    public function createRequest(array $data): StockTransfer
    {
        // Validate business rules
        if (empty($data['source_branch_name']) || empty($data['destination_branch_name'])) {
            throw new TransactionValidationException('Source and destination branches are required');
        }

        if ($data['source_branch_name'] === $data['destination_branch_name']) {
            throw new TransactionValidationException('Source and destination branches cannot be the same');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw new TransactionValidationException('At least one item is required');
        }

        // Validate each item
        foreach ($data['items'] as $item) {
            if (empty($item['currency_code'])) {
                throw new TransactionValidationException('Currency code is required for each item');
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new TransactionValidationException('Quantity must be a positive number');
            }

            if (! isset($item['rate']) || $item['rate'] <= 0) {
                throw new TransactionValidationException('Rate must be a positive number');
            }

            // Verify currency exists
            if (! Currency::where('code', $item['currency_code'])->exists()) {
                throw new TransactionValidationException("Currency {$item['currency_code']} does not exist");
            }
        }

        // Calculate and validate total value
        $calculatedTotal = '0';
        foreach ($data['items'] as $item) {
            $itemValue = $this->mathService->multiply($item['quantity'], $item['rate']);
            $calculatedTotal = $this->mathService->add($calculatedTotal, $itemValue);
        }

        if (isset($data['total_value_myr']) && $this->mathService->compare($data['total_value_myr'], $calculatedTotal) !== 0) {
            throw new TransactionValidationException('Total value does not match sum of item values');
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
        if (! $this->requester->isManager() && ! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only managers can approve transfers');
        }

        if (! $transfer->isPending()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer is not in requested status');
        }

        $transfer->approveByBranchManager($this->requester);
    }

    public function approveByHQ(StockTransfer $transfer): void
    {
        if (! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only HQ (Admin) can approve transfers');
        }

        if ($transfer->status !== StockTransferStatus::BranchManagerApproved) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be BM-approved before HQ approval');
        }

        $transfer->approveByHQ($this->requester);
    }

    public function dispatch(StockTransfer $transfer): void
    {
        if (! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only admin can dispatch transfers');
        }

        if ($transfer->status !== StockTransferStatus::HqApproved) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be HQ-approved before dispatch');
        }

        // Outbound stock movement: the SOURCE branch gives up the full
        // transferred quantity (Sell-side sign from CurrencyPositionService).
        // Row locks are held until this transaction commits, so concurrent
        // dispatches cannot both pass the balance check.
        DB::transaction(function () use ($transfer) {
            $transfer->loadMissing('items');
            $sourceBranchKey = $this->positionBranchKey($transfer->source_branch_name);

            foreach ($transfer->items as $item) {
                $this->decrementSourcePosition(
                    $sourceBranchKey,
                    (string) $item->currency_code,
                    (string) $item->quantity
                );
            }

            $transfer->dispatch();
        });
    }

    public function receiveItems(StockTransfer $transfer, array $items): void
    {
        if (! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only admin can receive items');
        }

        if ($transfer->status !== StockTransferStatus::InTransit) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit to receive items');
        }

        DB::transaction(function () use ($transfer, $items) {
            $itemIds = collect($items)->pluck('id');
            /** @var Collection<(int|string), StockTransferItem> $existingItems */
            $existingItems = $transfer->items()
                ->whereIn('id', $itemIds)
                ->get()
                ->keyBy('id');

            // Inbound stock movement key: received quantities land on the
            // DESTINATION branch position (Buy-side sign).
            $destinationBranchKey = $this->positionBranchKey($transfer->destination_branch_name);

            foreach ($items as $itemData) {
                if (! isset($itemData['id']) || ! is_numeric($itemData['id'])) {
                    throw new TransactionValidationException('Each item must have a valid numeric id');
                }

                if (! isset($itemData['quantity_received']) || ! is_numeric($itemData['quantity_received'])) {
                    throw new TransactionValidationException('Each item must have a numeric quantity_received');
                }

                $item = $existingItems->get($itemData['id']);
                if ($item) {
                    // Negative receipts would inflate in-transit stock.
                    if ($this->mathService->compare((string) $itemData['quantity_received'], '0') < 0) {
                        throw new TransactionValidationException(
                            "Quantity received for item {$item->id} cannot be negative"
                        );
                    }

                    // Guard against over-receipt: receiving more than the
                    // transferred quantity would drive in-transit negative.
                    if ($this->mathService->compare((string) $itemData['quantity_received'], (string) $item->quantity) > 0) {
                        throw new TransactionValidationException(
                            "Quantity received for item {$item->id} exceeds the transferred quantity ({$item->quantity})"
                        );
                    }

                    $item->update([
                        'quantity_received' => $itemData['quantity_received'],
                        'quantity_in_transit' => $this->mathService->subtract((string) $item->quantity, (string) $itemData['quantity_received']),
                    ]);

                    // Destination branch position grows by what actually arrived.
                    $this->incrementDestinationPosition(
                        $destinationBranchKey,
                        (string) $item->currency_code,
                        (string) $itemData['quantity_received']
                    );

                    if ($item->hasVariance()) {
                        $item->update(['variance_notes' => "Variance: {$item->variance}"]);

                        if ($this->mathService->compare($item->quantity, '0') > 0) {
                            $variancePercent = $this->mathService->multiply(
                                $this->mathService->divide(
                                    $this->mathService->abs((string) $item->variance),
                                    (string) $item->quantity
                                ),
                                '100'
                            );
                            if ($this->mathService->compare($variancePercent, '5') > 0) {
                                $this->auditService->logStockTransferEvent(
                                    'stock_transfer_variance_exceeded',
                                    (int) $transfer->id,
                                    ['new_values' => [
                                        'item_id' => $item->id,
                                        'currency' => $item->currency_code,
                                        'variance_percent' => $variancePercent,
                                    ]],
                                );
                            }
                        }
                    }
                }
            }

            $transfer->load('items');
            $allFullyReceived = $transfer->items->every(fn ($item) => $item->isFullyReceived());
            $transfer->update([
                'status' => $allFullyReceived
                    ? StockTransferStatus::Received->value
                    : StockTransferStatus::PartiallyReceived->value,
            ]);
        });
    }

    public function complete(StockTransfer $transfer): void
    {
        if (! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only admin can complete transfers');
        }

        if (! in_array($transfer->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived])) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit or partially received to complete');
        }

        // Finalise the inbound movement: whatever was dispatched but not yet
        // received (dispatched quantity minus receipts) lands on the DESTINATION
        // branch at completion, so receiveItems() + complete() together deliver
        // exactly what dispatch() removed from the source.
        DB::transaction(function () use ($transfer) {
            $transfer->loadMissing('items');
            $destinationBranchKey = $this->positionBranchKey($transfer->destination_branch_name);

            foreach ($transfer->items as $item) {
                $received = (string) ($item->quantity_received ?? '0');
                $outstanding = $this->mathService->subtract((string) $item->quantity, $received);

                $this->incrementDestinationPosition(
                    $destinationBranchKey,
                    (string) $item->currency_code,
                    $outstanding
                );
            }

            $transfer->complete();
        });
    }

    public function cancel(StockTransfer $transfer, string $reason): void
    {
        if (! $this->requester->isManager() && ! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only managers can cancel transfers');
        }

        if ($transfer->isCompleted()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Cannot cancel a completed transfer');
        }

        if ($transfer->status === StockTransferStatus::Cancelled) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer is already cancelled');
        }

        $transfer->cancel($reason);
    }

    public function reject(StockTransfer $transfer, string $reason = ''): void
    {
        if (! $this->requester->isAdmin()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only admin can reject transfers');
        }

        if (! in_array($transfer->status, [
            StockTransferStatus::Requested,
            StockTransferStatus::BranchManagerApproved,
            StockTransferStatus::HqApproved,
            StockTransferStatus::InTransit,
        ])) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer cannot be rejected in current state');
        }

        DB::transaction(function () use ($transfer, $reason) {
            $transfer->update(['status' => StockTransferStatus::Rejected]);
            $this->auditService->logStockTransferEvent(
                'stock_transfer_rejected',
                (int) $transfer->id,
                ['reason' => $reason],
            );
        });
    }

    public function getPendingTransfers(): Collection
    {
        return StockTransfer::pending()->with('items')->get();
    }

    public function getInTransitTransfers(): Collection
    {
        return StockTransfer::inTransit()->with('items')->get();
    }

    public function getTransfersByBranch(string $branchName, int $limit = 500): Collection
    {
        return StockTransfer::where('source_branch_name', $branchName)
            ->orWhere('destination_branch_name', $branchName)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve a stock-transfer branch identifier (branch name or code, as
     * stored on transfers) to the currency_positions.branch_id key used by the
     * booking path, which writes (string) branches.id via CurrencyPositionService.
     * Falls back to the raw identifier so legacy free-text names still map to a
     * stable position key instead of being silently skipped.
     */
    private function positionBranchKey(string $identifier): string
    {
        if (trim($identifier) === '') {
            return $identifier;
        }

        $branchId = Branch::query()
            ->where('name', $identifier)
            ->orWhere('code', $identifier)
            ->value('id');

        return $branchId !== null ? (string) $branchId : $identifier;
    }

    /**
     * Outbound leg: subtract from the SOURCE branch currency position.
     *
     * Sign convention mirrors CurrencyPositionService::updatePosition where a
     * Sell (outbound) SUBTRACTS. Uses the getPositionWithLock lock pattern
     * (pessimistic row lock) so two concurrent dispatches cannot both pass the
     * balance check and drive the position negative.
     *
     * @throws InsufficientStockException If the subtraction would go below zero
     */
    private function decrementSourcePosition(string $branchKey, string $currencyCode, string $amount): void
    {
        $position = $this->positionLocks()->findForUpdate($branchKey, $currencyCode);

        $available = $position !== null ? (string) $position->quantity : '0';

        if ($this->mathService->compare($available, $amount) < 0) {
            throw new InsufficientStockException($currencyCode, $amount, $available);
        }

        $position->update([
            'quantity' => $this->mathService->subtract($available, $amount),
        ]);
    }

    /**
     * Inbound leg: add to the DESTINATION branch currency position.
     *
     * Sign convention mirrors updatePosition where a Buy (inbound) ADDS. The
     * lock service's zero-baseline lock-or-create mirrors its Buy path so a
     * branch that never held this currency can still receive it.
     */
    private function incrementDestinationPosition(string $branchKey, string $currencyCode, string $amount): void
    {
        if ($this->mathService->compare($amount, '0') <= 0) {
            return;
        }

        $position = $this->positionLocks()->lock($branchKey, $currencyCode);

        $position->update([
            'quantity' => $this->mathService->add((string) $position->quantity, $amount),
        ]);
    }

    private function positionLocks(): CurrencyPositionLockService
    {
        return $this->positionLockService ??= app(CurrencyPositionLockService::class);
    }
}
