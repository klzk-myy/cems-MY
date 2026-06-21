<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevaluationEntry extends BaseModel
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
        'old_rate' => MoneyCast::class.':6',
        'new_rate' => MoneyCast::class.':6',
        'position_amount' => MoneyCast::class,
        'gain_loss_amount' => MoneyCast::class,
        'revaluation_date' => 'date',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
