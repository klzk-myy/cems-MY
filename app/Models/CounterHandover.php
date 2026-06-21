<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterHandover extends BaseModel
{
    use HasFactory;

    protected $with = ['fromUser', 'toUser', 'supervisor'];

    protected $fillable = [
        'counter_session_id',
        'from_user_id',
        'to_user_id',
        'supervisor_id',
        'handover_time',
        'physical_count_verified',
        'variance_myr',
        'variance_notes',
        'acknowledged_at',
        'yellow_variance',
    ];

    protected $casts = [
        'handover_time' => 'datetime',
        'physical_count_verified' => 'boolean',
        'variance_myr' => MoneyCast::class,
        'acknowledged_at' => 'datetime',
        'yellow_variance' => 'boolean',
    ];

    public function getSessionAttribute()
    {
        return $this->counterSession;
    }

    public function counterSession(): BelongsTo
    {
        return $this->belongsTo(CounterSession::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
