<?php

namespace App\Models;

use App\Enums\HighRiskCountryRiskLevel;

class HighRiskCountry extends BaseModel
{
    protected $primaryKey = 'country_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'country_code',
        'country_name',
        'risk_level',
        'source',
        'list_date',
    ];

    protected $casts = [
        'list_date' => 'date',
        'risk_level' => HighRiskCountryRiskLevel::class,
    ];
}
