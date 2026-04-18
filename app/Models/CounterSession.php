<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterSession extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'session_date' => 'date',
        'status' => CounterSessionStatus::class,
    ];

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tellerAllocation()
    {
        return $this->belongsTo(TellerAllocation::class);
    }

    public function openedByUser()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function handovers()
    {
        return $this->hasMany(CounterHandover::class);
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

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isHandedOver(): bool
    {
        return $this->status->isHandedOver();
    }
}
