<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRiskHistory extends Model
{
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
        'old_rating' => 'string',
        'new_rating' => 'string',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
