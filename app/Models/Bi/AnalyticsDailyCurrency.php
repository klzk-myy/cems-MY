<?php

namespace App\Models\Bi;

use App\Casts\MoneyCast;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-branch, per-currency daily rollup of completed transactions.
 *
 * @property int $id
 * @property Carbon $date
 * @property int|null $branch_id
 * @property string $currency_code
 * @property string $buy_qty
 * @property string $sell_qty
 * @property string $buy_volume_myr
 * @property string $sell_volume_myr
 * @property string $realized_margin_myr
 * @property Carbon|null $computed_at
 * @property-read Branch|null $branch
 * @property-read Currency $currency
 */
class AnalyticsDailyCurrency extends BaseModel
{
    protected $table = 'analytics_daily_currency';

    protected $fillable = [
        'date',
        'branch_id',
        'currency_code',
        'buy_qty',
        'sell_qty',
        'buy_volume_myr',
        'sell_volume_myr',
        'realized_margin_myr',
        'computed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'buy_qty' => MoneyCast::class,
            'sell_qty' => MoneyCast::class,
            'buy_volume_myr' => MoneyCast::class,
            'sell_volume_myr' => MoneyCast::class,
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
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
