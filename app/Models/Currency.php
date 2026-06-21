<?php

namespace App\Models;

use App\Models\Traits\HasCodeAndName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends BaseModel
{
    use HasCodeAndName, HasFactory, SoftDeletes;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_active' => 'boolean',
    ];

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'currency_code');
    }
}
