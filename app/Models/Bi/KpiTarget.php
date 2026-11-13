<?php

namespace App\Models\Bi;

use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operational KPI target — the plan's §8 open question 2. GL budgets
 * cover revenue; volume and turnaround targets are not expressible there.
 *
 * A null branch_id is the company-wide target; a branch row overrides it
 * for that branch, matching how the BI rollups scope.
 *
 * @property int $id
 * @property string $kpi_metric
 * @property int|null $branch_id
 * @property string $period
 * @property string $target_value
 * @property int|null $updated_by
 * @property-read Branch|null $branch
 * @property-read User|null $updater
 */
class KpiTarget extends BaseModel
{
    protected $table = 'kpi_targets';

    public const PERIOD_DAY = 'day';

    public const PERIOD_MONTH = 'month';

    protected $fillable = [
        'kpi_metric',
        'branch_id',
        'period',
        'target_value',
        'updated_by',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'target_value' => 'decimal:4',
        'updated_by' => 'integer',
    ];

    use BelongsToBranch;

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
