<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPool extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'currency_code',
        'available_balance',
        'allocated_balance',
    ];

    protected $casts = [
        'available_balance' => MoneyCast::class,
        'allocated_balance' => MoneyCast::class,
    ];

    protected MathService $mathService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->mathService = app(MathService::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasAvailable(string $amount): bool
    {
        return $this->mathService->compare($this->available_balance, $amount) >= 0;
    }

    public function allocate(string $amount): bool
    {
        if (! $this->hasAvailable($amount)) {
            return false;
        }

        $this->available_balance = $this->mathService->subtract($this->available_balance, $amount);
        $this->allocated_balance = $this->mathService->add($this->allocated_balance, $amount);
        $this->save();

        return true;
    }

    public function deallocate(string $amount): bool
    {
        if ($this->mathService->compare($this->allocated_balance, $amount) < 0) {
            return false;
        }

        $this->available_balance = $this->mathService->add($this->available_balance, $amount);
        $this->allocated_balance = $this->mathService->subtract($this->allocated_balance, $amount);
        $this->save();

        return true;
    }

    public function releaseFunds(string $amount): bool
    {
        if ($this->mathService->compare($this->allocated_balance, $amount) < 0) {
            return false;
        }

        $this->available_balance = $this->mathService->add($this->available_balance, $amount);
        $this->allocated_balance = $this->mathService->subtract($this->allocated_balance, $amount);
        $this->save();

        return true;
    }
}
