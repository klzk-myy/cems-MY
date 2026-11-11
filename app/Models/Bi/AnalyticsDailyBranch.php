<?php

namespace App\Models\Bi;

use App\Casts\MoneyCast;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Per-branch daily rollup, rebuilt by AnalyticsSnapshotBuilder.
 *
 * @property int $id
 * @property Carbon $date
 * @property int|null $branch_id
 * @property int $tx_count
 * @property int $completed_count
 * @property int $rejected_count
 * @property int $cancelled_count
 * @property int $approved_count
 * @property string $approval_seconds_total
 * @property int $rate_override_count
 * @property string $buy_volume_myr
 * @property string $sell_volume_myr
 * @property int $unique_customers
 * @property Carbon|null $computed_at
 * @property-read Branch|null $branch
 */
class AnalyticsDailyBranch extends BaseModel
{
    protected $table = 'analytics_daily_branch';

    protected $fillable = [
        'date',
        'branch_id',
        'tx_count',
        'completed_count',
        'rejected_count',
        'cancelled_count',
        'approved_count',
        'approval_seconds_total',
        'rate_override_count',
        'buy_volume_myr',
        'sell_volume_myr',
        'unique_customers',
        'computed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approval_seconds_total' => 'decimal:2',
            'buy_volume_myr' => MoneyCast::class,
            'sell_volume_myr' => MoneyCast::class,
            'computed_at' => 'datetime',
        ];
    }

    use BelongsToBranch;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }
}
