<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock Transfer Item Model
 *
 * Represents an individual currency item in a stock transfer.
 *
 * @property int $id
 * @property int $stock_transfer_id
 * @property string $currency_code
 * @property string $quantity
 * @property string $rate
 * @property string $value_myr
 * @property string $quantity_received
 * @property string $quantity_in_transit
 * @property string|null $variance_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StockTransferItem extends Model
{
    use HasFactory;

    protected $table = 'stock_transfer_items';

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

    /**
     * Get the stock transfer this item belongs to.
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Get the currency for this item.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Calculate the variance between expected and received quantity.
     */
    public function getVarianceAttribute(): string
    {
        return bcsub((string) $this->quantity, (string) $this->quantity_received, 4);
    }

    /**
     * Check if item has any variance.
     */
    public function hasVariance(): bool
    {
        return bccomp((string) $this->quantity, (string) $this->quantity_received, 4) !== 0;
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return bccomp((string) $this->quantity, (string) $this->quantity_received, 4) === 0;
    }
}
