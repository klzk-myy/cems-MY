<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataBreachAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_type',
        'severity',
        'description',
        'record_count',
        'triggered_by',
        'ip_address',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function triggerUser()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
