<?php

namespace App\Models;

use App\Enums\StockReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $fillable = [
        'transaction_id',
        'currency_code',
        'till_id',
        'amount_foreign',
        'status',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'amount_foreign' => 'string',
        'expires_at' => 'datetime',
        'status' => StockReservationStatus::class,
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === StockReservationStatus::Pending;
    }

    public function isConsumed(): bool
    {
        return $this->status === StockReservationStatus::Consumed;
    }

    public function isReleased(): bool
    {
        return $this->status === StockReservationStatus::Released;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->isPast();
    }
}
