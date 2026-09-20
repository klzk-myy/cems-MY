<?php

namespace App\Services\Transaction;

use App\Enums\AccountMappingKey;
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
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\System\MathService;
use App\Support\ActorContext;
use App\ValueObjects\QuoteConvention;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    protected ?User $requester = null;

    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        protected BranchPoolService $branchPoolService,
        protected AccountingService $accountingService,
        protected AccountMappingService $accountMappingService,
        protected CurrencyPositionService $positionService,
        protected RateManagementService $rateManagementService,
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

        // Resolve real branch identity up front — the name/code inputs are
        // display hints; every downstream check runs on the FK ids.
        $sourceBranch = $this->branchFromIdentifier($data['source_branch_name']);
        $destinationBranch = $this->branchFromIdentifier($data['destination_branch_name']);

        if (! $sourceBranch) {
            throw new TransactionValidationException(message: 'Source branch could not be resolved');
        }

        if (! $destinationBranch) {
            throw new TransactionValidationException(message: 'Destination branch could not be resolved');
        }

        $isWithinBranch = $sourceBranch->id === $destinationBranch->id;

        // Within-branch transfers (teller-to-teller stock/cash reallocation)
        // are allowed for managers and admins. Inter-branch transfers
        // require distinct source and destination.
        if ($isWithinBranch && ! $this->requester()->role->canTransferTellerStock()) {
            throw new TransactionValidationException(message: 'You do not have permission to perform within-branch stock transfers');
        }

        // Head-office branches hold no foreign stock — they cannot be a
        // transfer source or destination.
        if ($sourceBranch->type === Branch::TYPE_HEAD_OFFICE
            || $destinationBranch->type === Branch::TYPE_HEAD_OFFICE) {
            throw new TransactionValidationException(message: 'Head office does not hold foreign stock and cannot participate in transfers');
        }

        // Maker check: a non-admin may only create transfers sourcing stock
        // from their own branch.
        $requester = $this->requester();
        if (! $requester->isAdmin() && (int) $requester->branch_id !== (int) $sourceBranch->id) {
            throw new TransactionValidationException(message: 'You can only create transfers sourcing stock from your own branch');
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw new TransactionValidationException(message: 'At least one item is required');
        }

        // Validate each item. A client-supplied 'rate' is accepted as a
        // display hint only — the stored rate is always computed server-side
        // (resolveItemRate) so GL legs cannot be steered by request input.
        foreach ($data['items'] as $item) {
            if (empty($item['currency_code'])) {
                throw new TransactionValidationException(message: 'Currency code is required for each item');
            }

            if (! isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new TransactionValidationException(message: 'Quantity must be a positive number');
            }

            // Verify currency exists
            if (! Currency::where('code', $item['currency_code'])->exists()) {
                throw new TransactionValidationException(message: "Currency {$item['currency_code']} does not exist");
            }
        }

        // Value each item at the source position's average_cost (cost basis
        // follows the stock), falling back to the day's mid rate card.
        $sourceBranchKey = (string) $sourceBranch->id;
        $items = collect($data['items'])->map(function (array $item) use ($sourceBranchKey, $sourceBranch) {
            $rate = $this->resolveItemRate(
                (string) $item['currency_code'],
                $sourceBranchKey,
                $sourceBranch
            );

            return [
                'currency_code' => $item['currency_code'],
                'quantity' => (string) $item['quantity'],
                'rate' => $rate,
                'value_myr' => $this->mathService->multiply((string) $item['quantity'], $rate),
            ];
        })->all();

        // Calculate and validate total value
        $calculatedTotal = '0';
        foreach ($items as $item) {
            $calculatedTotal = $this->mathService->add($calculatedTotal, $item['value_myr']);
        }

        if (isset($data['total_value_myr']) && $this->mathService->compare($data['total_value_myr'], $calculatedTotal) !== 0) {
            throw new TransactionValidationException(message: 'Total value does not match sum of item values');
        }

        return DB::transaction(function () use ($data, $items, $calculatedTotal, $sourceBranch, $destinationBranch) {
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'type' => $data['type'] ?? StockTransfer::TYPE_STANDARD,
                'status' => StockTransferStatus::Requested->value,
                // Real identity on the FK columns; names persist as display
                // snapshots in canonical form.
                'source_branch_id' => $sourceBranch->id,
                'destination_branch_id' => $destinationBranch->id,
                'source_branch_name' => $sourceBranch->name,
                'destination_branch_name' => $destinationBranch->name,
                'requested_by' => $this->requester()->id,
                'requested_at' => now(),
                'notes' => $data['notes'] ?? null,
                'total_value_myr' => $calculatedTotal,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create($item);
            }

            return $transfer->load('items');
        });
    }

    /**
     * Per-unit MYR valuation for a transfer item. The stock's own cost basis
     * (source branch position average_cost) leads so GL legs carry the same
     * value the inventory was booked at; when the branch has never held the
     * currency the day's mid rate card stands in. Base-currency (MYR) items
     * value at par.
     */
    private function resolveItemRate(string $currencyCode, string $sourceBranchKey, ?Branch $sourceBranch): string
    {
        if ($currencyCode === Currency::baseCurrency()) {
            return '1';
        }

        $position = $this->positionService->getPosition($currencyCode, $sourceBranchKey);

        if ($position !== null && $this->mathService->compare((string) $position->average_cost, '0') > 0) {
            return (string) $position->average_cost;
        }

        $rateCard = $this->rateManagementService->getRateCard(
            $currencyCode,
            $sourceBranch !== null ? (int) $sourceBranch->id : null
        );

        if ($rateCard === null) {
            throw new TransactionValidationException(
                message: "Cannot value {$currencyCode}: the source branch has no cost basis and no rate card is set"
            );
        }

        $midQuoted = $this->mathService->divide(
            $this->mathService->add((string) $rateCard->rate_buy, (string) $rateCard->rate_sell),
            '2'
        );

        return QuoteConvention::for($rateCard)->toPerUnit($midQuoted);
    }

    public function approveByBranchManager(StockTransfer $transfer): void
    {
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can approve transfers');
        }

        $isWithinBranch = $this->isWithinBranchTransfer($transfer);

        if (! $isWithinBranch && $transfer->requested_by === $requester->id) {
            throw new TransactionApprovalException((int) $transfer->id, 'The requesting branch cannot approve its own transfer');
        }

        // Maker/taker: the DESTINATION branch manager (taker) approves the
        // request created by the source branch (maker). HQ is not involved.
        // Within-branch transfers skip this check — the same branch manager
        // who created the transfer may also approve it.
        if (! $isWithinBranch && ! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'destination')) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch manager can approve this transfer');
        }

        // For within-branch transfers, verify the requester belongs to that branch
        if ($isWithinBranch && ! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'source')) {
            throw new TransactionApprovalException((int) $transfer->id, 'You can only approve transfers within your own branch');
        }

        // Re-check the state transition on the locked row: a concurrent
        // approve/reject could have committed since the controller loaded
        // this instance.
        DB::transaction(function () use ($transfer, $requester) {
            $locked = $this->lockedTransfer($transfer);

            if (! $locked->isPending()) {
                throw new TransactionApprovalException((int) $locked->id, 'Transfer is not in requested status');
            }

            $locked->approveByBranchManager($requester);
        });
    }

    public function dispatch(StockTransfer $transfer): void
    {
        $requester = $this->requester();

        if (! $requester->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can dispatch transfers');
        }

        if (! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'source')) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the source branch can dispatch this transfer');
        }

        // Outbound stock movement: the SOURCE branch gives up the full
        // transferred quantity (Sell-side sign from CurrencyPositionService).
        // Row locks are held until this transaction commits, so concurrent
        // dispatches cannot both pass the balance check.
        DB::transaction(function () use ($transfer) {
            // Lock the transfer row and re-check status inside the
            // transaction — a concurrent dispatch/complete could have
            // committed since this instance was loaded. Taker approval
            // (BranchManagerApproved) is sufficient to dispatch; HqApproved
            // remains accepted for pre-maker/taker transfers.
            $transfer = $this->lockedTransfer($transfer);

            if (! in_array($transfer->status, [StockTransferStatus::BranchManagerApproved, StockTransferStatus::HqApproved], true)) {
                throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be approved by the destination branch before dispatch');
            }

            $transfer->loadMissing('items');
            $sourceBranchKey = $this->transferBranchKey($transfer, 'source');
            $sourceBranch = $this->transferBranch($transfer, 'source');

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

            // GL leg: the transferred value leaves the source branch's
            // ledger chain into inter-branch clearing (Dr 2300 / Cr
            // inventory.{CCY}); the receive/complete legs settle it out.
            // Within-branch transfers net to zero on one position row, so
            // no entry is posted for them.
            if (! $this->isWithinBranchTransfer($transfer)) {
                $glAmounts = [];
                foreach ($transfer->items as $item) {
                    $currencyCode = (string) $item->currency_code;
                    $glAmounts[$currencyCode] = $this->mathService->add(
                        $glAmounts[$currencyCode] ?? '0',
                        (string) $item->value_myr
                    );
                }
                $this->postTransferGl($transfer, $sourceBranch, $glAmounts, 'dispatch');
            }

            // The full item quantity is now in transit; receipts whittle it
            // down and the terminal transitions zero it.
            foreach ($transfer->items as $item) {
                $item->update(['quantity_in_transit' => (string) $item->quantity]);
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

        if (! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'destination')) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch can receive this transfer');
        }

        DB::transaction(function () use ($transfer, $items, $requester) {
            // Lock the transfer row first, then items — every mutating path
            // takes the same order, and the status re-check under the lock
            // stops a stale InTransit view from receiving after completion.
            $transfer = $this->lockedTransfer($transfer);

            if ($transfer->status !== StockTransferStatus::InTransit) {
                throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit to receive items');
            }

            $itemIds = collect($items)->pluck('id');
            /** @var Collection<(int|string), StockTransferItem> $existingItems */
            $existingItems = $transfer->items()
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Inbound stock movement key: received quantities land on the
            // DESTINATION branch position (Buy-side sign).
            $destinationBranchKey = $this->transferBranchKey($transfer, 'destination');
            $destinationBranch = $this->transferBranch($transfer, 'destination');

            $glAmounts = [];
            foreach ($items as $itemData) {
                $this->receiveItem(
                    $transfer,
                    $itemData,
                    $existingItems,
                    $destinationBranchKey,
                    $destinationBranch,
                    $requester,
                    $glAmounts
                );
            }

            // GL leg: what actually arrived lands on the destination branch's
            // ledger chain (Dr inventory.{CCY} / Cr 2300 clearing).
            if (! $this->isWithinBranchTransfer($transfer)) {
                $this->postTransferGl($transfer, $destinationBranch, $glAmounts, 'receipt');
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
     * @param  array<string, string>  $glAmounts  Accumulated currency_code => MYR received value for the clearing-pair journal
     */
    private function receiveItem(
        StockTransfer $transfer,
        array $itemData,
        Collection $existingItems,
        string $destinationBranchKey,
        ?Branch $destinationBranch,
        User $requester,
        array &$glAmounts
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

        // Destination branch position grows by what actually arrived, at
        // the item's cost basis (source average_cost fixed at request time).
        $this->incrementDestinationPosition(
            $destinationBranchKey,
            (string) $item->currency_code,
            $receivedDelta,
            (string) $item->rate
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

        if ($this->mathService->compare($receivedDelta, '0') > 0) {
            $currencyCode = (string) $item->currency_code;
            $glAmounts[$currencyCode] = $this->mathService->add(
                $glAmounts[$currencyCode] ?? '0',
                $this->mathService->multiply($receivedDelta, (string) $item->rate)
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

        if (! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'destination')) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch can complete this transfer');
        }

        // Finalise the inbound movement: whatever was dispatched but not yet
        // received (dispatched quantity minus receipts) lands on the DESTINATION
        // branch at completion, so receiveItems() + complete() together deliver
        // exactly what dispatch() removed from the source.
        DB::transaction(function () use ($transfer, $requester) {
            // Lock the transfer row and re-check status: a concurrent
            // complete/cancel could have committed since this instance was
            // loaded. Received is included — fully-received transfers would
            // otherwise be stranded (outstanding is zero for every item, so
            // completion only finalises the status).
            $transfer = $this->lockedTransfer($transfer);

            if (! in_array($transfer->status, [StockTransferStatus::InTransit, StockTransferStatus::PartiallyReceived, StockTransferStatus::Received])) {
                throw new TransactionApprovalException((int) $transfer->id, 'Transfer must be in transit, partially received, or received to complete');
            }

            // Lock the items before reading quantity_received — a concurrent
            // receiveItems must not interleave with the outstanding read.
            $transfer->setRelation(
                'items',
                $transfer->items()->lockForUpdate()->get()
            );
            $destinationBranchKey = $this->transferBranchKey($transfer, 'destination');
            $destinationBranch = $this->transferBranch($transfer, 'destination');

            $glAmounts = [];
            foreach ($transfer->items as $item) {
                $received = (string) ($item->quantity_received ?? '0');
                $outstanding = $this->mathService->subtract((string) $item->quantity, $received);

                $this->incrementDestinationPosition(
                    $destinationBranchKey,
                    (string) $item->currency_code,
                    $outstanding,
                    (string) $item->rate
                );

                if ($destinationBranch && $this->mathService->compare($outstanding, '0') > 0) {
                    $this->poolService()->replenish(
                        $destinationBranch,
                        (string) $item->currency_code,
                        $outstanding,
                        $requester->id
                    );
                }

                if ($this->mathService->compare($outstanding, '0') > 0) {
                    $currencyCode = (string) $item->currency_code;
                    $glAmounts[$currencyCode] = $this->mathService->add(
                        $glAmounts[$currencyCode] ?? '0',
                        $this->mathService->multiply($outstanding, (string) $item->rate)
                    );
                }
            }

            // GL leg: the dispatched-but-unreceived remainder lands on the
            // destination branch (Dr inventory.{CCY} / Cr 2300 clearing).
            if (! $this->isWithinBranchTransfer($transfer)) {
                $this->postTransferGl($transfer, $destinationBranch, $glAmounts, 'receipt');
            }

            // Terminal state: nothing is in transit once the transfer
            // completes — the outstanding remainder was just delivered.
            $transfer->items()->update(['quantity_in_transit' => '0']);

            $transfer->complete();
        });
    }

    public function cancel(StockTransfer $transfer, string $reason): void
    {
        if (! $this->requester()->role->canPerform(Permission::ManageStockTransfers)) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only users permitted to manage stock transfers can cancel transfers');
        }

        DB::transaction(function () use ($transfer, $reason) {
            // Status is re-checked on the locked row — a concurrent
            // complete could have committed since this instance was loaded,
            // and cancelling after completion must not resurrect the stock.
            $transfer = $this->lockedTransfer($transfer);

            if ($transfer->isCompleted()) {
                throw new TransactionApprovalException((int) $transfer->id, 'Cannot cancel a completed transfer');
            }

            if ($transfer->status === StockTransferStatus::Cancelled) {
                throw new TransactionApprovalException((int) $transfer->id, 'Transfer is already cancelled');
            }

            $this->returnInFlightStockToSource($transfer);

            // Terminal state: in-flight stock was returned to source — it is
            // no longer "in transit".
            $transfer->items()->update(['quantity_in_transit' => '0']);

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

        if (! $requester->isAdmin() && ! $this->requesterMatchesTransferBranch($transfer, 'destination')) {
            throw new TransactionApprovalException((int) $transfer->id, 'Only the destination branch manager can reject this transfer');
        }

        DB::transaction(function () use ($transfer, $reason) {
            // Status is re-checked on the locked row — a concurrent dispatch
            // or receive could have committed since this instance was loaded.
            $transfer = $this->lockedTransfer($transfer);

            if (! in_array($transfer->status, [
                StockTransferStatus::Requested,
                StockTransferStatus::BranchManagerApproved,
                StockTransferStatus::HqApproved,
                StockTransferStatus::InTransit,
            ])) {
                throw new TransactionApprovalException((int) $transfer->id, 'Transfer cannot be rejected in current state');
            }

            $this->returnInFlightStockToSource($transfer);
            $transfer->items()->update(['quantity_in_transit' => '0']);
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
        $branchId = $this->branchFromIdentifier($branchName)?->id;

        return StockTransfer::where(function ($q) use ($branchName, $branchId) {
            if ($branchId !== null) {
                $q->where('source_branch_id', $branchId)
                    ->orWhere('destination_branch_id', $branchId);
            }
            // Legacy rows predating the FK columns only carry the name.
            $q->orWhere('source_branch_name', $branchName)
                ->orWhere('destination_branch_name', $branchName);
        })
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
        $sourceBranchKey = $this->transferBranchKey($transfer, 'source');
        $sourceBranch = $this->transferBranch($transfer, 'source');

        // Items are locked for the unreceived read — the enclosing caller
        // already holds the transfer row lock, so this ordering (transfer,
        // then items) matches receiveItems()/complete() and stays
        // deadlock-free.
        $transfer->setRelation('items', $transfer->items()->lockForUpdate()->get());

        $glAmounts = [];
        foreach ($transfer->items as $item) {
            $unreceived = $this->mathService->subtract(
                (string) $item->quantity,
                (string) ($item->quantity_received ?? '0')
            );

            if ($this->mathService->compare($unreceived, '0') <= 0) {
                continue;
            }

            $glAmounts[(string) $item->currency_code] = $this->mathService->add(
                $glAmounts[(string) $item->currency_code] ?? '0',
                $this->mathService->multiply($unreceived, (string) $item->rate)
            );

            // Returned stock re-enters the source position at the item's
            // original cost basis, keeping derived columns consistent.
            $this->positionService->adjustForTransfer(
                $sourceBranchKey,
                (string) $item->currency_code,
                $unreceived,
                'add',
                (string) $item->rate
            );

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

        // GL leg: returned in-transit stock comes back onto the source
        // branch's ledger chain (Dr inventory.{CCY} / Cr 2300 clearing).
        if (! $this->isWithinBranchTransfer($transfer)) {
            $this->postTransferGl($transfer, $sourceBranch, $glAmounts, 'receipt');
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
     * Position key for one side of a persisted transfer: the real branch FK
     * when present, the legacy name/code resolution otherwise.
     */
    private function transferBranchKey(StockTransfer $transfer, string $side): string
    {
        $branchId = $transfer->{"{$side}_branch_id"};

        return $branchId !== null
            ? (string) $branchId
            : $this->positionBranchKey((string) $transfer->{"{$side}_branch_name"});
    }

    /**
     * Branch model for one side of a persisted transfer: FK lookup first,
     * legacy name/code resolution as fallback.
     */
    private function transferBranch(StockTransfer $transfer, string $side): ?Branch
    {
        $branchId = $transfer->{"{$side}_branch_id"};

        return $branchId !== null
            ? Branch::query()->whereKey($branchId)->first()
            : $this->branchFromIdentifier((string) $transfer->{"{$side}_branch_name"});
    }

    /**
     * Whether the acting user's branch is the given side of the transfer.
     * FK comparison when the id is stored; legacy name/code resolution
     * otherwise. Fails closed when unresolvable.
     */
    private function requesterMatchesTransferBranch(StockTransfer $transfer, string $side): bool
    {
        $branchId = $transfer->{"{$side}_branch_id"};

        if ($branchId === null) {
            return $this->requesterBranchMatches($transfer->{"{$side}_branch_name"});
        }

        return (int) $branchId === (int) $this->requester()->branch_id;
    }

    /**
     * Within-branch transfer test. FK comparison when both ids are stored —
     * the names are only snapshots and may differ in form while resolving
     * to the same branch; legacy rows fall back to the name comparison.
     */
    private function isWithinBranchTransfer(StockTransfer $transfer): bool
    {
        if ($transfer->source_branch_id !== null && $transfer->destination_branch_id !== null) {
            return (int) $transfer->source_branch_id === (int) $transfer->destination_branch_id;
        }

        return $transfer->source_branch_name === $transfer->destination_branch_name;
    }

    /**
     * Re-fetch the transfer under a pessimistic lock inside the caller's
     * transaction. Route-model-bound instances are stale the moment they
     * reach the service; every state transition re-reads the row so a
     * concurrent commit cannot be overwritten.
     */
    private function lockedTransfer(StockTransfer $transfer): StockTransfer
    {
        /** @var StockTransfer $locked */
        $locked = StockTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /**
     * Outbound leg: subtract from the SOURCE branch currency position via
     * CurrencyPositionService — quantity, cost basis, and the derived money
     * columns stay consistent, and stock promised to pending Sell
     * reservations is not dispatchable.
     *
     * @throws InsufficientStockException If the subtraction would go below zero
     */
    private function decrementSourcePosition(string $branchKey, string $currencyCode, string $quantity): void
    {
        $this->positionService->adjustForTransfer($branchKey, $currencyCode, $quantity, 'subtract');
    }

    /**
     * Inbound leg: add to the DESTINATION branch currency position via
     * CurrencyPositionService at the item's cost basis — a branch that never
     * held the currency still receives it (lock-or-create).
     */
    private function incrementDestinationPosition(string $branchKey, string $currencyCode, string $quantity, ?string $costBasisRate = null): void
    {
        if ($this->mathService->compare($quantity, '0') <= 0) {
            return;
        }

        $this->positionService->adjustForTransfer($branchKey, $currencyCode, $quantity, 'add', $costBasisRate);
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

    /**
     * Post the GL leg of a stock movement through the inter-branch clearing
     * account (suspense.hq / 2300). Journal entries carry a single branch_id,
     * so a transfer posts one entry per side of the movement: dispatch credits
     * the currency's inventory account on the SOURCE branch's ledger chain and
     * debits clearing; receipt legs (receive / complete / cancel-return) debit
     * inventory on the branch where the stock landed and credit clearing. The
     * clearing account therefore holds the value of stock in transit and nets
     * to zero once every dispatched quantity is accounted for. MYR items move
     * through the cash.myr account rather than the pooled forex inventory.
     * Unresolvable branch identifiers post to the company-wide (null) chain so
     * global account totals still move correctly.
     *
     * @param  array<string, string>  $currencyAmountsMyr  currency_code => MYR value moved
     */
    private function postTransferGl(StockTransfer $transfer, ?Branch $branch, array $currencyAmountsMyr, string $direction): void
    {
        $clearingAccount = $this->accountMappingService->code(AccountMappingKey::SuspenseHq);

        $lines = [];
        $total = '0';
        foreach ($currencyAmountsMyr as $currencyCode => $amountMyr) {
            $amountMyr = (string) $amountMyr;

            if ($this->mathService->compare($amountMyr, '0') <= 0) {
                continue;
            }

            $accountCode = $currencyCode === Currency::baseCurrency()
                ? $this->accountMappingService->code(AccountMappingKey::CashMyr)
                : $this->accountMappingService->forCurrency('inventory', $currencyCode);

            $lines[] = $direction === 'dispatch'
                ? ['account_code' => $accountCode, 'credit' => $amountMyr, 'description' => "Transfer {$transfer->transfer_number} — {$currencyCode} dispatched"]
                : ['account_code' => $accountCode, 'debit' => $amountMyr, 'description' => "Transfer {$transfer->transfer_number} — {$currencyCode} received"];

            $total = $this->mathService->add($total, $amountMyr);
        }

        if ($lines === []) {
            return;
        }

        $lines[] = $direction === 'dispatch'
            ? ['account_code' => $clearingAccount, 'debit' => $total, 'description' => "Transfer {$transfer->transfer_number} — in transit"]
            : ['account_code' => $clearingAccount, 'credit' => $total, 'description' => "Transfer {$transfer->transfer_number} — in transit"];

        $this->accountingService->createJournalEntry(
            $lines,
            'StockTransfer',
            (int) $transfer->id,
            "Stock transfer {$transfer->transfer_number} ({$direction})",
            null,
            $this->requester()->id,
            $branch?->id
        );
    }
}
