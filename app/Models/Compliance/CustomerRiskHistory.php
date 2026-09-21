<?php

namespace App\Models\Compliance;

use App\Enums\RiskRating;
use App\Models\BaseModel;
use App\Models\Customer;
use App\Models\User;
use Database\Factories\Compliance\CustomerRiskHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskHistory extends BaseModel
{
    /** @use HasFactory<CustomerRiskHistoryFactory> */
    use HasFactory;

    protected $table = 'customer_risk_history';

    protected $fillable = [
        'customer_id',
        'old_score',
        'new_score',
        'old_rating',
        'new_rating',
        'change_reason',
        'assessed_by',
    ];

    protected $casts = [
        'old_score' => 'integer',
        'new_score' => 'integer',
        'old_rating' => RiskRating::class,
        'new_rating' => RiskRating::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
