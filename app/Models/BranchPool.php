<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'available_balance',
        'allocated_balance',
    ];

    protected $casts = [
        'available_balance' => 'decimal:4',
        'allocated_balance' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasAvailable(string $amount): bool
    {
        return bccomp($this->available_balance, $amount, 4) >= 0;
    }

    public function allocate(string $amount): bool
    {
        if (! $this->hasAvailable($amount)) {
            return false;
        }

        $this->available_balance = bcsub($this->available_balance, $amount, 4);
        $this->allocated_balance = bcadd($this->allocated_balance, $amount, 4);
        $this->save();

        return true;
    }

    public function deallocate(string $amount): bool
    {
        if (bccomp($this->allocated_balance, $amount, 4) < 0) {
            return false;
        }

        $this->available_balance = bcadd($this->available_balance, $amount, 4);
        $this->allocated_balance = bcsub($this->allocated_balance, $amount, 4);
        $this->save();

        return true;
    }

    public function releaseFunds(string $amount): bool
    {
        if (bccomp($this->allocated_balance, $amount, 4) < 0) {
            return false;
        }

        $this->available_balance = bcadd($this->available_balance, $amount, 4);
        $this->allocated_balance = bcsub($this->allocated_balance, $amount, 4);
        $this->save();

        return true;
    }
}
