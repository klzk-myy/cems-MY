<?php

namespace App\Models\Traits;

use App\Models\Currency;
/**
 * @property string $currency_code
 */
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCurrency
{
    public function initializeBelongsToCurrency(): void
    {
        $this->mergeFillable(['currency_code']);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function scopeForCurrency($query, string $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }
}
