<?php

namespace App\Policies;

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

        return $this->branchMatches($user, $stockTransfer->source_branch_name)
            || $this->branchMatches($user, $stockTransfer->destination_branch_name);
    }

    /**
     * Determine whether the user can approve the transfer (taker approval).
     * Maker/taker model: the DESTINATION branch manager approves the request
     * created by the source branch. Self-approval is prohibited.
     */
    public function approveBranchManager(User $user, StockTransfer $stockTransfer): bool
    {
        if ($stockTransfer->requested_by === $user->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->branchMatches($user, $stockTransfer->destination_branch_name);
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

        return $user->isManager()
            && $this->branchMatches($user, $stockTransfer->source_branch_name);
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
     * Determine whether the user can approve the transfer at HQ.
     * Admins only.
     */
    public function approveHq(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->isAdmin();
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

        return $user->isManager()
            && $this->branchMatches($user, $stockTransfer->destination_branch_name);
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

        return $this->branchMatches($user, $stockTransfer->destination_branch_name);
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

    /**
     * Whether the user's branch matches the given transfer branch identifier.
     * Returns false when the identifier cannot be resolved (fail-closed).
     */
    private function branchMatches(User $user, ?string $identifier): bool
    {
        $branchId = $this->branchIdFromName($identifier);

        if ($branchId === null) {
            return false;
        }

        return (int) $branchId === (int) $user->branch_id;
    }
}
