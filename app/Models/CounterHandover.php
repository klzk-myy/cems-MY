<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterHandover extends Model
{
    use HasFactory;

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
        'variance_myr' => 'decimal:2',
        'acknowledged_at' => 'datetime',
        'yellow_variance' => 'boolean',
    ];

    public function getSessionAttribute()
    {
        return $this->counterSession;
    }

    public function counterSession()
    {
        return $this->belongsTo(CounterSession::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
