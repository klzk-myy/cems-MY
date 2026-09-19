<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\BcmathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $currency_code
 * @property string $available_balance
 * @property string $allocated_balance
 * @property string $total_balance
 */
class BranchPool extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'available_balance',
        'allocated_balance',
        'total_balance',
    ];

    protected $casts = [
        'available_balance' => MoneyCast::class,
        'allocated_balance' => MoneyCast::class,
        'total_balance' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasAvailable(string $quantity): bool
    {
        return BcmathHelper::compare($this->available_balance, $quantity) >= 0;
    }

    public function allocate(string $quantity): bool
    {
        if (! $this->hasAvailable($quantity)) {
            return false;
        }

        $this->available_balance = BcmathHelper::subtract($this->available_balance, $quantity);
        $this->allocated_balance = BcmathHelper::add($this->allocated_balance, $quantity);
        $this->save();

        return true;
    }

    public function deallocate(string $quantity): bool
    {
        if (BcmathHelper::compare($this->allocated_balance, $quantity) < 0) {
            return false;
        }

        $this->available_balance = BcmathHelper::add($this->available_balance, $quantity);
        $this->allocated_balance = BcmathHelper::subtract($this->allocated_balance, $quantity);
        $this->save();

        return true;
    }

    public function releaseFunds(string $quantity): bool
    {
        if (BcmathHelper::compare($this->allocated_balance, $quantity) < 0) {
            return false;
        }

        $this->available_balance = BcmathHelper::add($this->available_balance, $quantity);
        $this->allocated_balance = BcmathHelper::subtract($this->allocated_balance, $quantity);
        $this->save();

        return true;
    }

    /**
     * Shrink the teller earmark without returning stock to available —
     * a sell removes the foreign currency from the branch entirely, so
     * the earmark must drop to keep available+allocated equal to stock
     * on hand. Clamps at zero (pools may carry historical drift) and
     * returns the amount actually consumed.
     */
    public function consumeAllocated(string $quantity): string
    {
        $consumed = BcmathHelper::compare($this->allocated_balance, $quantity) >= 0
            ? $quantity
            : $this->allocated_balance;

        if (BcmathHelper::compare($consumed, '0') <= 0) {
            return '0';
        }

        $this->allocated_balance = BcmathHelper::subtract($this->allocated_balance, $consumed);
        $this->save();

        return $consumed;
    }

    /**
     * Grow the teller earmark for stock that entered custody outside the
     * pool — a buy brings in foreign currency the customer sold to the
     * teller, which was never drawn from available.
     */
    public function growAllocated(string $quantity): void
    {
        $this->allocated_balance = BcmathHelper::add($this->allocated_balance, $quantity);
        $this->save();
    }
}
