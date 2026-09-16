<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $counter_id
 * @property int $user_id
 * @property Carbon $session_date
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int $opened_by
 * @property int|null $closed_by
 * @property int|null $teller_allocation_id
 * @property string|null $requested_amount_myr
 * @property string|null $daily_limit_myr
 * @property CounterSessionStatus|null $status
 * @property string|null $notes
 * @property bool|null $physical_count_verified
 * @property string|null $handover_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CounterSession extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'counter_id',
        'user_id',
        'session_date',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'status',
        'notes',
        'physical_count_verified',
        'handover_notes',
        'daily_limit_myr',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'session_date' => 'date',
        'status' => CounterSessionStatus::class,
        'daily_limit_myr' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Counter, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TellerAllocation, $this>
     */
    public function tellerAllocation(): BelongsTo
    {
        return $this->belongsTo(TellerAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CounterHandover, $this>
     */
    public function handovers(): HasMany
    {
        return $this->hasMany(CounterHandover::class);
    }

    /**
     * Check if the session is open.
     */
    public function isOpen(): bool
    {
        return $this->status === CounterSessionStatus::Open;
    }

    /**
     * Till key for this session's till balances: the counter code when the
     * counter exists, else the counter id.
     */
    public function tillCode(): string
    {
        return $this->counter->code ?? (string) $this->counter_id;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', CounterSessionStatus::Open->value);
    }

    public function scopeForCounter($query, $counterId)
    {
        return $query->where('counter_id', $counterId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('session_date', $date);
    }
}
