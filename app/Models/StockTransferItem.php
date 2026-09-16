<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_transfer_id
 * @property string $currency_code
 * @property string $quantity
 * @property string|null $rate
 * @property string|null $value_myr
 * @property string|null $quantity_received
 * @property string|null $quantity_in_transit
 * @property string $pool_debited
 * @property string|null $variance_notes
 * @property-read string $variance
 */
class StockTransferItem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'currency_code',
        'quantity',
        'rate',
        'value_myr',
        'quantity_received',
        'quantity_in_transit',
        'pool_debited',
        'variance_notes',
    ];

    protected $casts = [
        'quantity' => MoneyCast::class,
        'rate' => MoneyCast::class.':8',
        'value_myr' => MoneyCast::class,
        'quantity_received' => MoneyCast::class,
        'quantity_in_transit' => MoneyCast::class,
        'pool_debited' => MoneyCast::class,
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
        return bccomp($this->receivedQuantity(), $this->quantityNumericString(), 4) >= 0;
    }

    public function hasVariance(): bool
    {
        return bccomp($this->receivedQuantity(), $this->quantityNumericString(), 4) !== 0;
    }

    public function getVarianceAttribute(): string
    {
        return bcsub($this->quantityNumericString(), $this->receivedQuantity(), 4);
    }

    /**
     * @return numeric-string
     */
    private function receivedQuantity(): string
    {
        $received = $this->quantity_received ?? '0';

        if (! is_numeric($received)) {
            throw new \InvalidArgumentException('quantity_received must be numeric.');
        }

        return $received;
    }

    /**
     * @return numeric-string
     */
    private function quantityNumericString(): string
    {
        $quantity = $this->quantity;

        if ($quantity === null || ! is_numeric($quantity)) {
            throw new \InvalidArgumentException('quantity must be numeric.');
        }

        return $quantity;
    }
}
