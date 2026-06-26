<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends BaseModel
{
    use HasFactory;

    protected $with = ['currency'];

    protected $fillable = [
        'stock_transfer_id',
        'currency_code',
        'quantity',
        'rate',
        'value_myr',
        'quantity_received',
        'quantity_in_transit',
        'variance_notes',
    ];

    protected $casts = [
        'quantity' => MoneyCast::class,
        'rate' => MoneyCast::class.':6',
        'value_myr' => MoneyCast::class,
        'quantity_received' => MoneyCast::class,
        'quantity_in_transit' => MoneyCast::class,
    ];

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function isFullyReceived(): bool
    {
        $received = $this->quantity_received ?? '0';

        return bccomp((string) $received, (string) $this->quantity, 4) >= 0;
    }

    public function hasVariance(): bool
    {
        $received = $this->quantity_received ?? '0';

        return bccomp((string) $received, (string) $this->quantity, 4) !== 0;
    }

    public function getVarianceAttribute(): string
    {
        $received = $this->quantity_received ?? '0';

        return bcsub((string) $this->quantity, (string) $received, 4);
    }
}
