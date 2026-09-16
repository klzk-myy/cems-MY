<?php

namespace App\Models;

use App\ValueObjects\QuoteConvention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency_code
 * @property int|null $branch_id
 * @property string $rate
 * @property int $rate_unit
 * @property bool $rate_inverse
 * @property string|null $spread_applied
 * @property Carbon $effective_date
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeRateHistory extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'rate',
        'rate_unit',
        'rate_inverse',
        'effective_date',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'rate_unit' => 'integer',
        'rate_inverse' => 'boolean',
        'effective_date' => 'date',
    ];

    /**
     * The quote convention this row snapshots (rate_unit + rate_inverse).
     */
    public function quoteConvention(): QuoteConvention
    {
        return QuoteConvention::for($this);
    }

    /**
     * Normalize this row's quoted rate to per-unit MYR (8 decimals).
     */
    public function perUnitRate(string $quoted): string
    {
        return $this->quoteConvention()->toPerUnit($quoted);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForCurrency(Builder $query, string $code): Builder
    {
        return $query->where('currency_code', $code);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('effective_date', [$from, $to]);
    }

    public static function getLatestRate(string $currencyCode, ?int $branchId = null): ?string
    {
        $query = self::forCurrency($currencyCode);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }
        $latest = $query->orderBy('effective_date', 'desc')->first();

        return $latest ? (string) $latest->rate : null;
    }
}
