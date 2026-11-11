<?php

namespace App\Models\Bi;

use App\Casts\MoneyCast;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-customer, per-branch monthly rollup of completed transactions —
 * the grain for cohort retention, repeat-rate, and LTV analytics.
 *
 * @property int $id
 * @property string $month 'YYYY-MM'
 * @property int $customer_id
 * @property int|null $branch_id
 * @property int $tx_count
 * @property string $volume_myr
 * @property string $realized_margin_myr
 * @property int $currencies_used
 * @property Carbon|null $computed_at
 * @property-read Customer $customer
 * @property-read Branch|null $branch
 */
class AnalyticsMonthlyCustomer extends BaseModel
{
    protected $table = 'analytics_monthly_customer';

    protected $fillable = [
        'month',
        'customer_id',
        'branch_id',
        'tx_count',
        'volume_myr',
        'realized_margin_myr',
        'currencies_used',
        'computed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'volume_myr' => MoneyCast::class,
            'realized_margin_myr' => MoneyCast::class,
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

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
