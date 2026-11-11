<?php

namespace App\Models\Bi;

use App\Casts\MoneyCast;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-teller daily rollup. approval_* columns measure the turnaround of
 * the teller's own transactions (created → approved), not approvals the
 * user performed on others' work.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $user_id
 * @property int|null $branch_id
 * @property int $tx_count
 * @property string $volume_myr
 * @property int $cancelled_count
 * @property int $rejected_count
 * @property int $rate_override_count
 * @property int $approved_count
 * @property string $approval_seconds_total
 * @property Carbon|null $computed_at
 * @property-read User $user
 * @property-read Branch|null $branch
 */
class AnalyticsDailyTeller extends BaseModel
{
    protected $table = 'analytics_daily_teller';

    protected $fillable = [
        'date',
        'user_id',
        'branch_id',
        'tx_count',
        'volume_myr',
        'cancelled_count',
        'rejected_count',
        'rate_override_count',
        'approved_count',
        'approval_seconds_total',
        'computed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'volume_myr' => MoneyCast::class,
        'approval_seconds_total' => 'decimal:2',
        'computed_at' => 'datetime',
    ];

    use BelongsToBranch;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
