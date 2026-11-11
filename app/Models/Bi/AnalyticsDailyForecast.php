<?php

namespace App\Models\Bi;

use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Predicted currency demand for one future date per branch×currency,
 * generated nightly by DemandForecaster from analytics_daily_currency.
 * branch_id NULL = consolidated all-branch prediction.
 *
 * @property int $id
 * @property Carbon $date
 * @property int|null $branch_id
 * @property string $currency_code
 * @property string $predicted_buy_qty
 * @property string $predicted_sell_qty
 * @property string $predicted_buy_volume_myr
 * @property string $predicted_sell_volume_myr
 * @property string $method
 * @property int $sample_days
 * @property-read Branch|null $branch
 */
class AnalyticsDailyForecast extends BaseModel
{
    use BelongsToBranch;

    protected $table = 'analytics_daily_forecast';

    protected $fillable = [
        'date',
        'branch_id',
        'currency_code',
        'predicted_buy_qty',
        'predicted_sell_qty',
        'predicted_buy_volume_myr',
        'predicted_sell_volume_myr',
        'method',
        'sample_days',
    ];

    protected $casts = [
        'date' => 'date',
        'predicted_buy_qty' => 'decimal:4',
        'predicted_sell_qty' => 'decimal:4',
        'predicted_buy_volume_myr' => 'decimal:4',
        'predicted_sell_volume_myr' => 'decimal:4',
        'sample_days' => 'integer',
    ];

    /**
     * Nullable branch scope matching the other BI models — null leaves
     * the query unfiltered. The consolidated all-branch view carries
     * branch_id NULL; select it explicitly via consolidated().
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId === null ? $query : $query->where('branch_id', $branchId);
    }

    /**
     * Consolidated all-branch forecast rows (branch_id NULL) — the
     * per-branch sums the forecaster writes alongside leaf rows.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeConsolidated(Builder $query): Builder
    {
        return $query->whereNull('branch_id');
    }
}
