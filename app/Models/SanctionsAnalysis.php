<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AnalysisType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionsAnalysis extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'analysis_type',
        'transaction_count',
        'total_amount',
        'analyzed_at',
    ];

    protected $casts = [
        'transaction_count' => 'integer',
        'total_amount' => MoneyCast::class,
        'analyzed_at' => 'datetime',
        'analysis_type' => AnalysisType::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
