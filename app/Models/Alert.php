<?php

namespace App\Models;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Models\Compliance\ComplianceCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $flagged_transaction_id
 * @property int $customer_id
 * @property ComplianceFlagType $type
 * @property AlertPriority $priority
 * @property int $risk_score
 * @property string|null $reason
 * @property string $source
 * @property int|null $assigned_to
 * @property int|null $case_id
 * @property FlagStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Alert extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'flagged_transaction_id',
        'customer_id',
        'type',
        'priority',
        'risk_score',
        'reason',
        'source',
        'assigned_to',
        'case_id',
        'status',
    ];

    protected $casts = [
        'type' => ComplianceFlagType::class,
        'priority' => AlertPriority::class,
        'status' => FlagStatus::class,
        'risk_score' => 'integer',
    ];

    /**
     * @return BelongsTo<FlaggedTransaction, $this>
     */
    public function flaggedTransaction(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByPriority(Builder $query, AlertPriority $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('case_id');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', FlagStatus::Resolved->value);
    }

    /**
     * Scope to filter critical priority alerts.
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('priority', AlertPriority::Critical);
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }
}
