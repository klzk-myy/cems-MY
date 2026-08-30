<?php

namespace App\Models;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $transaction_id
 * @property int|null $customer_id
 * @property ComplianceFlagType $flag_type
 * @property string $flag_reason
 * @property string|null $severity Finding severity label
 * @property FlagStatus|null $status
 * @property int|null $assigned_to
 * @property int|null $reviewed_by
 * @property string|null $notes
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class FlaggedTransaction extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'flag_type',
        'flag_reason',
        'severity',
        'status',
        'assigned_to',
        'reviewed_by',
        'notes',
        'resolved_at',
        'customer_id',
    ];

    protected $casts = [
        'flag_type' => ComplianceFlagType::class,
        'status' => FlagStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to filter open (unresolved) flags.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FlagStatus::Open);
    }

    /**
     * Scope to filter high priority flags.
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('flag_type', ['Sanction_Match', 'Structuring', 'Velocity'])
            ->where('status', '!=', FlagStatus::Resolved);
    }
}
