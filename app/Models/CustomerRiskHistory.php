<?php

namespace App\Models;

use App\Enums\RiskRating;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskHistory extends BaseModel
{
    protected $table = 'customer_risk_history';

    protected $fillable = [
        'customer_id',
        'previous_score',
        'new_score',
        'previous_rating',
        'new_rating',
        'change_reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'previous_score' => 'integer',
        'new_score' => 'integer',
        'previous_rating' => RiskRating::class,
        'new_rating' => RiskRating::class,
        'changed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
