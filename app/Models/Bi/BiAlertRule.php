<?php

namespace App\Models\Bi;

use App\Enums\BiAlertComparison;
use App\Enums\KpiMetric;
use App\Enums\SystemAlertLevel;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\Traits\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Threshold rule evaluated daily against the analytics rollups by
 * BiAlertEvaluator; breaches raise a SystemAlert (and email when the
 * level is warning/critical). branch_id null = consolidated view.
 *
 * @property int $id
 * @property string $metric
 * @property BiAlertComparison $comparison
 * @property string $threshold
 * @property int|null $branch_id
 * @property SystemAlertLevel $level
 * @property array<int, string>|null $notification_recipients
 * @property bool $is_active
 * @property Carbon|null $last_evaluated_at
 * @property Carbon|null $last_triggered_at
 * @property int|null $created_by
 * @property-read Branch|null $branch
 * @property-read User|null $creator
 */
class BiAlertRule extends BaseModel
{
    use BelongsToBranch;

    protected $table = 'bi_alert_rules';

    protected $fillable = [
        'metric',
        'comparison',
        'threshold',
        'branch_id',
        'level',
        'notification_recipients',
        'is_active',
        'last_evaluated_at',
        'last_triggered_at',
        'created_by',
    ];

    protected $casts = [
        'comparison' => BiAlertComparison::class,
        'level' => SystemAlertLevel::class,
        'threshold' => 'decimal:4',
        'notification_recipients' => 'array',
        'is_active' => 'boolean',
        'last_evaluated_at' => 'datetime',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Metrics the evaluator can resolve via KpiService::periodMetrics().
     *
     * @return array<string, string>
     */
    public static function availableMetrics(): array
    {
        return KpiMetric::labelMap();
    }

    /**
     * @return array<string, string>
     */
    public static function availableComparisons(): array
    {
        return array_combine(
            array_map(fn (BiAlertComparison $c) => $c->value, BiAlertComparison::cases()),
            array_map(fn (BiAlertComparison $c) => $c->label(), BiAlertComparison::cases()),
        );
    }

    /**
     * Rollup branch scoping — null means all branches, matching the other
     * BI models (the trait's int-only signature does not apply here).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId === null ? $query : $query->where('branch_id', $branchId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
