<?php

namespace App\Models\Traits;

use App\Models\Branch;
/**
 * @property int|null $branch_id
 */
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    public function initializeBelongsToBranch(): void
    {
        $this->mergeFillable(['branch_id']);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
