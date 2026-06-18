<?php

namespace App\Models;

use App\Enums\CounterSessionStatus;
use App\Enums\CounterStatus;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Counter extends BaseModel
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'status' => CounterStatus::class,
    ];

    /**
     * Get the route key for the model.
     * This allows route model binding to use 'code' instead of 'id'.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function scopeActive($query)
    {
        return $query->where('status', CounterStatus::Active->value);
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
