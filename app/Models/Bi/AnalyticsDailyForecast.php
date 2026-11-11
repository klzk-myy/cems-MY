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

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'predicted_buy_qty' => 'decimal:4',
            'predicted_sell_qty' => 'decimal:4',
            'predicted_buy_volume_myr' => 'decimal:4',
            'predicted_sell_volume_myr' => 'decimal:4',
            'sample_days' => 'integer',
        ];
    }

    /**
     * Nullable branch scope matching the other BI models — null means
     * all branches (the consolidated rows carry branch_id NULL).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId === null
            ? $query->whereNull('branch_id')
            : $query->where('branch_id', $branchId);
    }
}
