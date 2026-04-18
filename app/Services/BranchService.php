<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BranchService
{
    /**
     * List active branches, ordered by is_main desc, then code
     */
    public function listBranches(): Collection
    {
        return Branch::active()
            ->orderByDesc('is_main')
            ->orderBy('code')
            ->get();
    }

    /**
     * Create a new branch
     */
    public function createBranch(array $data): Branch
    {
        return Branch::create($data);
    }

    /**
     * Update an existing branch
     */
    public function updateBranch(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->fresh();
    }

    /**
     * Deactivate a branch (sets is_active = false)
     *
     * @throws \InvalidArgumentException if attempting to deactivate the main (HQ) branch
     */
    public function deactivateBranch(Branch $branch): Branch
    {
        if ($branch->is_main) {
            throw new \InvalidArgumentException('Cannot deactivate the main branch (HQ).');
        }

        $branch->update(['is_active' => false]);

        return $branch->fresh();
    }

    /**
     * Get branch summary with counts
     *
     * @return array{counters_count: int, users_count: int, recent_transactions_count: int, recent_journal_entries_count: int}
     */
    public function getBranchSummary(Branch $branch): array
    {
        $recentStart = Carbon::now()->subDays(30)->startOfDay();

        return [
            'counters_count' => $branch->counters()->count(),
            'users_count' => $branch->users()->count(),
            'recent_transactions_count' => $branch->transactions()->where('created_at', '>=', $recentStart)->count(),
            'recent_journal_entries_count' => $branch->journalEntries()->where('created_at', '>=', $recentStart)->count(),
        ];
    }

    /**
     * Get all branches including inactive (for admin)
     */
    public function getAllBranchesIncludingInactive(): Collection
    {
        return Branch::orderByDesc('is_main')
            ->orderBy('code')
            ->get();
    }
}
