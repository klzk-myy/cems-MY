<?php

namespace App\Services\Transaction;

use App\Enums\Permission;
use App\Enums\StockTransferStatus;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TransactionApprovalException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\System\MathService;
use App\Support\ActorContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    protected ?User $requester = null;

    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        protected CurrencyPositionLockService $positionLockService,
        protected BranchPoolService $branchPoolService,
        ?User $requester = null,
    ) {
        $this->requester = $requester ?? $this->resolveRequester();
    }

    /**
     * The acting user, resolved lazily — a service instance built before the
     * auth guard populated (early container resolution) would otherwise hold
     * a permanently null requester.
     */
    protected function requester(): User
    {
        $this->requester ??= $this->resolveRequester();

        if (! $this->requester instanceof User) {
            throw new UnauthorizedException('An authenticated user is required for stock transfer operations');
        }

        return $this->requester;
    }

    private function resolveRequester(): ?User
    {
        return ActorContext::capture()->user;
    }

    /**
     * Whether the acting user's branch matches the transfer's stored branch
     * identifier (name or code). Fails closed when unresolvable.
     */
    private function requesterBranchMatches(?string $identifier): bool
    {
        if ($identifier === null || trim($identifier) === '') {
            return false;
        }

        $branchId = Branch::query()
            ->where('name', $identifier)
            ->orWhere('code', $identifier)
            ->value('id');

        return $branchId !== null && (int) $branchId === (int) $this->requester()->branch_id;
    }

    public function createRequest(array $data): StockTransfer
    {
        // Validate business rules
        if (empty($data['source_branch_name']) || empty($data['destination_branch_name'])) {
            throw new TransactionValidationException(message: 'Source and destination branches are required');
        }

        $isWithinBranch = $data['source_branch_name'] === $data['destination_branch_name'];

        // Within-branch transfers (teller-to-teller stock/cash reallocation)
        // are allowed for managers and admins. Inter-branch transfers
        // require distinct source and destination.
        if ($isWithinBranch && ! $this->requester()->role->canTransferTellerStock()) {
            throw new TransactionValidationException(message: 'You do not have permission to perform within-branch stock transfers');
        }

        // Head-office branches hold no foreign stock — they cannot be a
        // transfer source or destination.
        $hqInvolved = Branch::query()
            ->where('type', Branch::TYPE_HEAD_OFFICE)
            ->where(fn ($q) => $q
                ->where('name', $data['source_branch_name'])
                ->orWhere('code', $data['source_branch_name'])
                ->orWhere('name', $data['destination_branch_name'])
                ->orWhere('code', $data['destination_branch_name']))
            ->exists();

        if ($hqInvolved) {
            throw new TransactionValidationException(message: 'Head office does not hold foreign stock and cannot participate in transfers');
        }

        // Maker check: a non-admin may only create transfers sourcing stock
        // from their own branch.
        $requester = $this->requester();
        if (! $requester->isAdmin() && ! $this->requesterBranchMatches($data['source_branch_name'])) {
            throw new TransactionValidationException(message: 'You can only create transfers sourcing stock from your own branch');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw new TransactionValidationException(message: 'At least one item is required');
        }

        // Validate each item
        foreach ($data['items'] as $item) {
            if (empty($item['currency_code'])) {
                throw new TransactionValidationException(message: 'Currency code is required for each item');
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new TransactionValidationException(message: 'Quantity must be a positive number');
            }

            if (! isset($item['rate']) || $item['rate'] <= 0) {
                throw new TransactionValidationException(message: 'Rate must be a positive number');
            }

            // Verify currency exists
            if (! Currency::where('code', $item['currency_code'])->exists()) {
                throw new TransactionValidationException(message: "Currency {$item['currency_code']} does not exist");
            }
        }

        // Calculate and validate total value
        $calculatedTotal = '0';
        foreach ($data['items'] as $item) {
            $itemValue = $this->mathService->multiply($item['quantity'], $item['rate']);
            $calculatedTotal = $this->mathService->add($calculatedTotal, $itemValue);
        }

        if (isset($data['total_value_myr']) && $this->mathService->compare($data['total_value_myr'], $calculatedTotal) !== 0) {
            throw new TransactionValidationException(message: 'Total value does not match sum of item values');
        }

        return DB::transaction(function () use ($data, $calculatedTotal) {
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'type' => $data['type'] ?? StockTransfer::TYPE_STANDARD,
                'status' => StockTransferStatus::Requested->value,
                'source_branch_name' => $data['source_branch_name'],
                'destination_branch_name' => $data['destination_branch_name'],
                'requested_by' => $this->requester()->id,
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
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can approve transfers');
        }

        if (! $transfer->isPending()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer is not in requested status');
        }

        $isWithinBranch = $transfer->source_branch_name === $transfer->destination_branch_name;

        if (! $isWithinBranch && $transfer->requested_by === $requester->id) {
            throw new TransactionApprovalException((int) $transfer->id, 'The requesting branch cannot approve its own transfer');
        }

        // Maker/taker: the DESTINATION branch manager (taker) approves the
        // request created by the source branch (maker). HQ is not involved.
        // Within-branch transfers skip this check — the same branch manager
        // who created the transfer may also approve it.
        if (! $isWithinBranch && ! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->destination_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch manager can approve this transfer');
        }

        // For within-branch transfers, verify the requester belongs to that branch
        if ($isWithinBranch && ! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->source_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'You can only approve transfers within your own branch');
        }

        $transfer->approveByBranchManager($requester);
    }

    public function dispatch(StockTransfer $transfer): void
    {
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can dispatch transfers');
        }

        if (! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->source_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the source branch can dispatch this transfer');
        }

        // Taker approval (BranchManagerApproved) is sufficient to dispatch.
        // HqApproved remains accepted for transfers created before the
        // maker/taker model removed the HQ step.
        if (! in_array($transfer->status, [StockTransferStatus::BranchManagerApproved, StockTransferStatus::HqApproved], true)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be approved by the destination branch before dispatch');
        }

        // Outbound stock movement: the SOURCE branch gives up the full
        // transferred quantity (Sell-side sign from CurrencyPositionService).
        // Row locks are held until this transaction commits, so concurrent
        // dispatches cannot both pass the balance check.
        DB::transaction(function () use ($transfer) {
            $transfer->loadMissing('items');
            $sourceBranchKey = $this->positionBranchKey($transfer->source_branch_name);
            $sourceBranch = $this->branchFromIdentifier($transfer->source_branch_name);

            foreach ($transfer->items as $item) {
                $this->decrementSourcePosition(
                    $sourceBranchKey,
                    (string) $item->currency_code,
                    (string) $item->quantity
                );

                // The same stock leaves the branch's teller-allocatable pool.
                // pool_debited records what the pool actually covered so a
                // cancel/reject return credits back no more than it gave.
                if ($sourceBranch) {
                    $debited = $this->poolService()->debit(
                        $sourceBranch,
                        (string) $item->currency_code,
                        (string) $item->quantity,
                        $this->requester()->id
                    );

                    $item->update(['pool_debited' => $debited]);
                }
            }

            $transfer->dispatch();
        });
    }

    public function receiveItems(StockTransfer $transfer, array $items): void
    {
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can receive items');
        }

        if (! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->destination_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch can receive this transfer');
        }

        if ($transfer->status !== StockTransferStatus::InTransit) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit to receive items');
        }

        DB::transaction(function () use ($transfer, $items, $requester) {
            $itemIds = collect($items)->pluck('id');
            /** @var Collection<(int|string), StockTransferItem> $existingItems */
            $existingItems = $transfer->items()
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Inbound stock movement key: received quantities land on the
            // DESTINATION branch position (Buy-side sign).
            $destinationBranchKey = $this->positionBranchKey($transfer->destination_branch_name);
            $destinationBranch = $this->branchFromIdentifier($transfer->destination_branch_name);

            foreach ($items as $itemData) {
                $this->receiveItem(
                    $transfer,
                    $itemData,
                    $existingItems,
                    $destinationBranchKey,
                    $destinationBranch,
                    $requester
                );
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

    /**
     * @param  array<string, mixed>  $itemData
     * @param  Collection<(int|string), StockTransferItem>  $existingItems
     */
    private function receiveItem(
        StockTransfer $transfer,
        array $itemData,
        Collection $existingItems,
        string $destinationBranchKey,
        ?Branch $destinationBranch,
        User $requester
    ): void {
        if (! isset($itemData['id']) || ! is_numeric($itemData['id'])) {
            throw new TransactionValidationException(message: 'Each item must have a valid numeric id');
        }

        if (! isset($itemData['quantity_received']) || ! is_numeric($itemData['quantity_received'])) {
            throw new TransactionValidationException(message: 'Each item must have a numeric quantity_received');
        }

        $item = $existingItems->get($itemData['id']);

        if (! $item) {
            return;
        }

        $newReceived = (string) $itemData['quantity_received'];

        // Negative receipts would inflate in-transit stock.
        if ($this->mathService->compare($newReceived, '0') < 0) {
            throw new TransactionValidationException(
                message: "Quantity received for item {$item->id} cannot be negative"
            );
        }

        // Guard against over-receipt: receiving more than the
        // transferred quantity would drive in-transit negative.
        if ($this->mathService->compare($newReceived, (string) $item->quantity) > 0) {
            throw new TransactionValidationException(
                message: "Quantity received for item {$item->id} exceeds the transferred quantity ({$item->quantity})"
            );
        }

        // Receipts are cumulative: the submitted quantity_received is
        // the item's new total, so only the delta since the last
        // receipt is credited. Re-submitting an earlier receipt can
        // therefore never double-credit the position or the pool.
        $previousReceived = (string) ($item->quantity_received ?? '0');

        if ($this->mathService->compare($newReceived, $previousReceived) < 0) {
            throw new TransactionValidationException(
                message: "Quantity received for item {$item->id} cannot be less than the previously received {$previousReceived}"
            );
        }

        $receivedDelta = $this->mathService->subtract($newReceived, $previousReceived);

        $item->update([
            'quantity_received' => $newReceived,
            'quantity_in_transit' => $this->mathService->subtract((string) $item->quantity, $newReceived),
        ]);

        // Destination branch position grows by what actually arrived.
        $this->incrementDestinationPosition(
            $destinationBranchKey,
            (string) $item->currency_code,
            $receivedDelta
        );

        // The arrived stock joins the branch's teller-allocatable pool.
        if ($destinationBranch && $this->mathService->compare($receivedDelta, '0') > 0) {
            $this->poolService()->replenish(
                $destinationBranch,
                (string) $item->currency_code,
                $receivedDelta,
                $requester->id
            );
        }

        $this->auditVarianceIfExceeded($item, (int) $transfer->id);
    }

    private function auditVarianceIfExceeded(StockTransferItem $item, int $transferId): void
    {
        if (! $item->hasVariance()) {
            return;
        }

        $item->update(['variance_notes' => "Variance: {$item->variance}"]);

        if ($this->mathService->compare($item->quantity, '0') <= 0) {
            return;
        }

        $variancePercent = $this->mathService->multiply(
            $this->mathService->divide(
                $this->mathService->abs((string) $item->variance),
                (string) $item->quantity
            ),
            '100'
        );

        if ($this->mathService->compare($variancePercent, '5') <= 0) {
            return;
        }

        $this->auditService->logStockTransferEvent(
            'stock_transfer_variance_exceeded',
            $transferId,
            ['new_values' => [
                'item_id' => $item->id,
                'currency' => $item->currency_code,
                'variance_percent' => $variancePercent,
            ]],
        );
    }

    public function complete(StockTransfer $transfer): void
    {
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can complete transfers');
        }

        if (! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->destination_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch can complete this transfer');
        }

        // Received is included: fully-received transfers would otherwise be
        // stranded — outstanding is zero for every item, so completion only
        // finalises the status.
        if (! in_array($transfer->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived, StockTransferStatus::Received])) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit, partially received, or received to complete');
        }

        // Finalise the inbound movement: whatever was dispatched but not yet
        // received (dispatched quantity minus receipts) lands on the DESTINATION
        // branch at completion, so receiveItems() + complete() together deliver
        // exactly what dispatch() removed from the source.
        DB::transaction(function () use ($transfer, $requester) {
            $transfer->loadMissing('items');
            $destinationBranchKey = $this->positionBranchKey($transfer->destination_branch_name);
            $destinationBranch = $this->branchFromIdentifier($transfer->destination_branch_name);

            foreach ($transfer->items as $item) {
                $received = (string) ($item->quantity_received ?? '0');
                $outstanding = $this->mathService->subtract((string) $item->quantity, $received);

                $this->incrementDestinationPosition(
                    $destinationBranchKey,
                    (string) $item->currency_code,
                    $outstanding
                );

                if ($destinationBranch && $this->mathService->compare($outstanding, '0') > 0) {
                    $this->poolService()->replenish(
                        $destinationBranch,
                        (string) $item->currency_code,
                        $outstanding,
                        $requester->id
                    );
                }
            }

            $transfer->complete();
        });
    }

    public function cancel(StockTransfer $transfer, string $reason): void
    {
        if (! $this->requester()->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can cancel transfers');
        }

        if ($transfer->isCompleted()) {
            throw new TransactionApprovalException((int) $transfer->id, 'Cannot cancel a completed transfer');
        }

        if ($transfer->status === StockTransferStatus::Cancelled) {
            throw new TransactionApprovalException((int) $transfer->id, 'Transfer is already cancelled');
        }

        DB::transaction(function () use ($transfer, $reason) {
            $this->returnInFlightStockToSource($transfer);
            $transfer->cancel($reason);
        });
    }

    public function reject(StockTransfer $transfer, string $reason = ''): void
    {
        $requester = $this->requester();

        // The taker (destination branch) rejects the maker's request; admin can
        // reject any transfer.
        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can reject transfers');
        }

        if (! $requester->isAdmin() && ! $this->requesterBranchMatches($transfer->destination_branch_name)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch manager can reject this transfer');
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
            $this->returnInFlightStockToSource($transfer);
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
     * Return dispatched-but-unreceived stock to the SOURCE branch on
     * cancel/reject. dispatch() removed the full quantity from the source;
     * receiveItems() lands only what arrived at the destination — without
     * this, the in-flight remainder would vanish from every position.
     */
    private function returnInFlightStockToSource(StockTransfer $transfer): void
    {
        if (! in_array($transfer->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived], true)) {
            return;
        }

        $transfer->loadMissing('items');
        $sourceBranchKey = $this->positionBranchKey($transfer->source_branch_name);
        $sourceBranch = $this->branchFromIdentifier($transfer->source_branch_name);

        foreach ($transfer->items as $item) {
            $unreceived = $this->mathService->subtract(
                (string) $item->quantity,
                (string) ($item->quantity_received ?? '0')
            );

            if ($this->mathService->compare($unreceived, '0') <= 0) {
                continue;
            }

            $position = $this->positionLocks()->lock($sourceBranchKey, (string) $item->currency_code);
            $position->update([
                'quantity' => $this->mathService->add((string) $position->quantity, $unreceived),
            ]);

            // Returned stock re-enters the source branch's allocatable pool,
            // capped at what dispatch actually took from it — pool debits are
            // clamped at the available balance, so crediting the full
            // unreceived amount could inflate the pool past its real share.
            if ($sourceBranch) {
                $poolReturn = $this->mathService->compare($unreceived, (string) $item->pool_debited) <= 0
                    ? $unreceived
                    : (string) $item->pool_debited;

                if ($this->mathService->compare($poolReturn, '0') > 0) {
                    $this->poolService()->replenish(
                        $sourceBranch,
                        (string) $item->currency_code,
                        $poolReturn,
                        $this->requester()->id
                    );
                }
            }
        }
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
        return $this->positionLockService;
    }

    private function poolService(): BranchPoolService
    {
        return $this->branchPoolService;
    }

    /**
     * Resolve a stock-transfer branch identifier (branch name or code, as
     * stored on transfers) to a Branch model for pool updates. Returns null
     * for unresolvable legacy free-text identifiers — the position ledger
     * still updates via its raw-key fallback, and the pool is skipped.
     */
    private function branchFromIdentifier(string $identifier): ?Branch
    {
        if (trim($identifier) === '') {
            return null;
        }

        return Branch::query()
            ->where('name', $identifier)
            ->orWhere('code', $identifier)
            ->first();
    }
}
