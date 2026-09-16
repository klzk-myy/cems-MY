<?php

namespace App\Models;

use App\Models\Traits\HasCodeAndName;
use App\ValueObjects\QuoteConvention;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 * @property int $decimal_places
 * @property int $rate_unit
 * @property bool $rate_inverse
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Currency extends BaseModel
{
    /**
     * ISO code of the settlement currency all local amounts are booked in.
     */
    public const BASE = 'MYR';

    use HasCodeAndName, HasFactory, SoftDeletes;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'rate_unit',
        'rate_inverse',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rate_unit' => 'integer',
        'rate_inverse' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * The settlement currency code, honoring the `cems.base_currency` config
     * override. Default is `Currency::BASE`.
     */
    public static function baseCurrency(): string
    {
        return (string) config('cems.base_currency', self::BASE);
    }

    /**
     * The quote convention (rate_unit + rate_inverse) governing how this
     * currency's rates are quoted.
     */
    public function quoteConvention(): QuoteConvention
    {
        return QuoteConvention::for($this);
    }

    /**
     * Quote conventions for many currency codes in one query, keyed by code.
     * Codes without a currency row are absent from the result — callers fall
     * back to `new QuoteConvention` (unit 1, direct).
     *
     * @param  iterable<string>  $codes
     * @return Collection<string, QuoteConvention>
     */
    public static function quoteConventions(iterable $codes): Collection
    {
        return static::whereIn('code', $codes)->get()->keyBy('code')->map->quoteConvention();
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'currency_code');
    }

    /**
     * Get exchange rate histories for this currency.
     */
    public function exchangeRateHistories(): HasMany
    {
        return $this->hasMany(ExchangeRateHistory::class, 'currency_code');
    }

    /**
     * Get revaluation entries for this currency.
     */
    public function revaluationEntries(): HasMany
    {
        return $this->hasMany(RevaluationEntry::class, 'currency_code');
    }

    /**
     * Get stock transfer items for this currency.
     */
    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'currency_code');
    }
}
