<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Counter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the route key for the model.
     * This allows route model binding to use 'code' instead of 'id'.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function sessions()
    {
        return $this->hasMany(CounterSession::class);
    }

    public function currentSession()
    {
        return $this->hasOne(CounterSession::class)
            ->where('session_date', now()->toDateString())
            ->where('status', CounterSessionStatus::Open->value)
            ->latest();
    }
}
