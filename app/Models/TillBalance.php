<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Traits\BelongsToBranch;
use App\Services\System\MathService;
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
 * @property string $transaction_total
 * @property string $foreign_total
 * @property string $buy_total_foreign
 * @property string $sell_total_foreign
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
        'foreign_total',
        'transaction_total',
        'buy_total_foreign',
        'sell_total_foreign',
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
            'foreign_total' => '0',
            'transaction_total' => '0',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
            'date' => $date,
            'opened_by' => $openedBy,
        ], $overrides));
    }

    protected $casts = [
        'opening_balance' => MoneyCast::class,
        'closing_balance' => MoneyCast::class,
        'variance' => MoneyCast::class,
        'foreign_total' => MoneyCast::class,
        'transaction_total' => MoneyCast::class,
        'buy_total_foreign' => MoneyCast::class,
        'sell_total_foreign' => MoneyCast::class,
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
     * For foreign currency: expected = opening_balance + buy_total_foreign - sell_total_foreign
     * This correctly tracks position for both buys (adds to position) and sells (reduces position)
     */
    public function getExpectedBalance(): string
    {
        $mathService = app(MathService::class);
        $opening = (string) $this->opening_balance;
        $buyTotal = $this->buy_total_foreign !== null ? (string) $this->buy_total_foreign : '0';
        $sellTotal = $this->sell_total_foreign !== null ? (string) $this->sell_total_foreign : '0';

        // net foreign = buys - sells (buys increase position, sells decrease position)
        $netForeign = $mathService->subtract($buyTotal, $sellTotal);

        return $mathService->add($opening, $netForeign);
    }

    /**
     * Calculate variance between closing balance and expected balance
     * Expected = opening_balance + foreign_total (transaction activity)
     */
    public function calculateVariance(): string
    {
        if ($this->closing_balance === null) {
            return '0';
        }

        $mathService = app(MathService::class);
        $closing = (string) $this->closing_balance;
        $expected = $this->getExpectedBalance();

        return $mathService->subtract($closing, $expected);
    }

    /**
     * Check if variance exceeds threshold
     */
    public function hasSignificantVariance(string $threshold = '100.00'): bool
    {
        $mathService = app(MathService::class);
        $variance = $this->calculateVariance();
        $absVariance = $mathService->abs($variance);

        return $mathService->compare($absVariance, $threshold) > 0;
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
