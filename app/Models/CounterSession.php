<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounterSession extends BaseModel
{
    use HasFactory;

    protected $with = ['user', 'openedByUser', 'closedByUser'];

    protected $fillable = [
        'counter_id',
        'user_id',
        'teller_allocation_id',
        'session_date',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'status',
        'notes',
        'physical_count_verified',
        'handover_notes',
        'requested_amount_myr',
        'daily_limit_myr',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'session_date' => 'date',
        'status' => CounterSessionStatus::class,
        'requested_amount_myr' => 'decimal:2',
        'daily_limit_myr' => 'decimal:2',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tellerAllocation(): BelongsTo
    {
        return $this->belongsTo(TellerAllocation::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

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
