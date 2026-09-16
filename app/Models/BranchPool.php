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

    public function hasAvailable(string $amount): bool
    {
        return BcmathHelper::compare($this->available_balance, $amount) >= 0;
    }

    public function allocate(string $amount): bool
    {
        if (! $this->hasAvailable($amount)) {
            return false;
        }

        $this->available_balance = BcmathHelper::subtract($this->available_balance, $amount);
        $this->allocated_balance = BcmathHelper::add($this->allocated_balance, $amount);
        $this->save();

        return true;
    }

    public function deallocate(string $amount): bool
    {
        if (BcmathHelper::compare($this->allocated_balance, $amount) < 0) {
            return false;
        }

        $this->available_balance = BcmathHelper::add($this->available_balance, $amount);
        $this->allocated_balance = BcmathHelper::subtract($this->allocated_balance, $amount);
        $this->save();

        return true;
    }

    public function releaseFunds(string $amount): bool
    {
        if (BcmathHelper::compare($this->allocated_balance, $amount) < 0) {
            return false;
        }

        $this->available_balance = BcmathHelper::add($this->available_balance, $amount);
        $this->allocated_balance = BcmathHelper::subtract($this->allocated_balance, $amount);
        $this->save();

        return true;
    }
}
