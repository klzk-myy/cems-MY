<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'counter_id',
        'session_id',
        'teller_id',
        'reason',
        'closed_at',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CounterSession::class);
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
