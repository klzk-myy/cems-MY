<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevaluationEntry extends Model
{
    protected $fillable = [
        'currency_code',
        'till_id',
        'old_rate',
        'new_rate',
        'position_amount',
        'gain_loss_amount',
        'revaluation_date',
        'posted_by',
    ];

    protected $casts = [
        'old_rate' => 'decimal:6',
        'new_rate' => 'decimal:6',
        'position_amount' => 'decimal:4',
        'gain_loss_amount' => 'decimal:4',
        'revaluation_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
