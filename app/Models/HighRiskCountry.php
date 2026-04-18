<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HighRiskCountry extends Model
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
    ];
}
