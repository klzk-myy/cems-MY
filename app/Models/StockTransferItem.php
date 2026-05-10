<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
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
        'quantity' => 'decimal:4',
        'rate' => 'decimal:6',
        'value_myr' => 'decimal:2',
        'quantity_received' => 'decimal:4',
        'quantity_in_transit' => 'decimal:4',
    ];

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }
}
