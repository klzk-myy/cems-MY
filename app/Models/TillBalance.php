<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Traits\BelongsToBranch;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $till_id
 * @property string $currency_code
 * @property int|null $branch_id
 * @property string $opening_balance
 * @property string|null $closing_balance
 * @property string|null $variance
 * @property string $transaction_total_myr
 * @property string $total_quantity
 * @property string $buy_quantity
 * @property string $sell_quantity
 * @property Carbon $date
 * @property int $opened_by
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property int|null $teller_allocation_id
 * @property-read Currency|null $currency
 * @property-read User|null $opener
 * @property-read User|null $closer
 * @property-read Counter|null $counter
 */
class TillBalance extends BaseModel
{
    use BelongsToBranch, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'till_id',
        'currency_code',
        'branch_id',
        'opening_balance',
        'closing_balance',
        'variance',
        'date',
        'opened_by',
        'closed_by',
        'closed_at',
        'notes',
        'total_quantity',
        'transaction_total_myr',
        'buy_quantity',
        'sell_quantity',
    ];

    /**
     * Create an open till-balance row.
     *
     * Single creation point so no call site can omit `branch_id` (branch
     * isolation depends on it) or the zeroed counters.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function openFor(
        string $tillId,
        string $currencyCode,
        ?int $branchId,
        string|int|float $openingBalance,
        string|Carbon $date,
        int $openedBy,
        array $overrides = []
    ): self {
        return self::create(array_merge([
            'till_id' => $tillId,
            'currency_code' => $currencyCode,
            'branch_id' => $branchId,
            'opening_balance' => $openingBalance,
            'closing_balance' => null,
            'variance' => null,
            'total_quantity' => '0',
            'transaction_total_myr' => '0',
            'buy_quantity' => '0',
            'sell_quantity' => '0',
            'date' => $date,
            'opened_by' => $openedBy,
        ], $overrides));
    }

    protected $casts = [
        'opening_balance' => MoneyCast::class,
        'closing_balance' => MoneyCast::class,
        'variance' => MoneyCast::class,
        'total_quantity' => MoneyCast::class,
        'transaction_total_myr' => MoneyCast::class,
        'buy_quantity' => MoneyCast::class,
        'sell_quantity' => MoneyCast::class,
        'date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<Counter, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'till_id', 'code');
    }

    public function tellerAllocation(): BelongsTo
    {
        return $this->belongsTo(TellerAllocation::class);
    }

    /**
     * Calculate the expected balance (opening + transaction activity)
     * For the base currency: expected = opening_balance + transaction_total_myr
     * For foreign currency: expected = opening_balance + buy_quantity - sell_quantity
     * This correctly tracks position for both buys (adds to position) and sells (reduces position)
     */
    public function getExpectedBalance(): string
    {
        $opening = (string) $this->opening_balance;

        if ($this->currency_code === Currency::baseCurrency()) {
            return BcmathHelper::add($opening, (string) ($this->transaction_total_myr ?? '0'));
        }

        $buyQuantity = $this->buy_quantity !== null ? (string) $this->buy_quantity : '0';
        $sellQuantity = $this->sell_quantity !== null ? (string) $this->sell_quantity : '0';

        // net foreign = buys - sells (buys increase position, sells decrease position)
        $netQuantity = BcmathHelper::subtract($buyQuantity, $sellQuantity);

        return BcmathHelper::add($opening, $netQuantity);
    }

    /**
     * Calculate variance between closing balance and expected balance
     * Expected = opening_balance + total_quantity (transaction activity)
     */
    public function calculateVariance(): string
    {
        if ($this->closing_balance === null) {
            return '0';
        }
        $closing = (string) $this->closing_balance;
        $expected = $this->getExpectedBalance();

        return BcmathHelper::subtract($closing, $expected);
    }

    /**
     * Check if variance exceeds threshold
     */
    public function hasSignificantVariance(string $threshold = '100.00'): bool
    {
        $variance = $this->calculateVariance();
        $absVariance = BcmathHelper::abs($variance);

        return BcmathHelper::compare($absVariance, $threshold) > 0;
    }

    /**
     * Scope for today's balances
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for open tills (not yet closed)
     */
    public function scopeOpen($query)
    {
        return $query->whereNull('closed_at');
    }

    /**
     * Scope for closed tills
     */
    public function scopeClosed($query)
    {
        return $query->whereNotNull('closed_at');
    }
}
