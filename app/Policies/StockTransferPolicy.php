<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    /**
     * Determine whether the user can view any stock transfers.
     * Reachable only by managers/admins (the controller enforces the role),
     * so any authenticated, branch-assigned user passes here.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can view the transfer.
     * Admins see every transfer; other users only those where their branch is
     * the source or the destination.
     */
    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->branchMatchesTransfer($user, $stockTransfer, 'source')
            || $this->branchMatchesTransfer($user, $stockTransfer, 'destination');
    }

    /**
     * Determine whether the user can approve the transfer (taker approval).
     * Maker/taker model: the DESTINATION branch manager approves the request
     * created by the source branch. Self-approval is prohibited.
     * Within-branch transfers (source === destination) allow the same
     * branch manager to approve.
     */
    public function approveBranchManager(User $user, StockTransfer $stockTransfer): bool
    {
        $isWithinBranch = $this->isWithinBranch($stockTransfer);

        if (! $isWithinBranch && $stockTransfer->requested_by === $user->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($isWithinBranch) {
            return $this->branchMatchesTransfer($user, $stockTransfer, 'source');
        }

        return $this->branchMatchesTransfer($user, $stockTransfer, 'destination');
    }

    /**
     * Determine whether the user can dispatch the transfer.
     * The maker (source branch manager) dispatches; admins can dispatch any.
     */
    public function dispatch(User $user, StockTransfer $stockTransfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->role->canPerform(Permission::ManageStockTransfers)
            && $this->branchMatchesTransfer($user, $stockTransfer, 'source');
    }

    /**
     * Determine whether the user can cancel the transfer.
     * The maker (source branch manager) cancels; admins can cancel any.
     */
    public function cancel(User $user, StockTransfer $stockTransfer): bool
    {
        return $this->dispatch($user, $stockTransfer);
    }

    /**
     * Determine whether the user can reject the transfer.
     * The taker (destination branch manager) rejects the maker's request;
     * admins can reject any transfer.
     */
    public function reject(User $user, StockTransfer $stockTransfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->role->canPerform(Permission::ManageStockTransfers)
            && $this->branchMatchesTransfer($user, $stockTransfer, 'destination');
    }

    /**
     * Determine whether the user can receive items for the transfer.
     * Only admins, or members of the transfer's DESTINATION branch.
     */
    public function receive(User $user, StockTransfer $stockTransfer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->branchMatchesTransfer($user, $stockTransfer, 'destination');
    }

    /**
     * Determine whether the user can complete the transfer.
     * Same branch constraint as receiving (destination branch).
     */
    public function complete(User $user, StockTransfer $stockTransfer): bool
    {
        return $this->receive($user, $stockTransfer);
    }

    /**
     * Name and code identifiers of the given user's branch, used for query-level
     * branch scoping of StockTransfer (which stores branch names, not IDs).
     * Returns an empty array when the user has no assigned branch (deny-all).
     *
     * @return array<int, string>
     */
    public static function branchIdentifiers(User $user): array
    {
        if ($user->branch_id === null) {
            return [];
        }

        return Branch::query()
            ->whereKey($user->branch_id)
            ->get(['name', 'code'])
            ->flatMap(fn (Branch $branch) => array_filter([$branch->name, $branch->code]))
            ->values()
            ->all();
    }

    /**
     * Resolve the transfer's branch identity for one side. New rows carry real
     * FKs; legacy rows only have the name/code snapshot, which is resolved
     * against the branches table as a fallback.
     */
    private function transferBranchId(StockTransfer $transfer, string $side): ?int
    {
        $fk = $side === 'source' ? $transfer->source_branch_id : $transfer->destination_branch_id;

        if ($fk !== null) {
            return (int) $fk;
        }

        $name = $side === 'source' ? $transfer->source_branch_name : $transfer->destination_branch_name;

        return $this->branchIdFromName($name);
    }

    /**
     * Whether the user's branch owns the given side of the transfer.
     * Returns false when the branch cannot be resolved (fail-closed).
     */
    private function branchMatchesTransfer(User $user, StockTransfer $transfer, string $side): bool
    {
        $branchId = $this->transferBranchId($transfer, $side);

        return $branchId !== null && $branchId === (int) $user->branch_id;
    }

    /**
     * Whether source and destination resolve to the same branch.
     */
    private function isWithinBranch(StockTransfer $transfer): bool
    {
        $source = $this->transferBranchId($transfer, 'source');
        $destination = $this->transferBranchId($transfer, 'destination');

        if ($source !== null && $destination !== null) {
            return $source === $destination;
        }

        // One side unresolvable: fall back to the stored name equality for
        // fully legacy rows.
        return $source === null && $destination === null
            && $transfer->source_branch_name === $transfer->destination_branch_name;
    }

    /**
     * Resolve a free-form branch identifier (name or code, as stored on the
     * transfer) to a branches.id. Returns null when unresolvable.
     */
    private function branchIdFromName(?string $identifier): ?int
    {
        if ($identifier === null || trim($identifier) === '') {
            return null;
        }

        return Branch::query()
            ->where('name', $identifier)
            ->orWhere('code', $identifier)
            ->value('id');
    }
}
