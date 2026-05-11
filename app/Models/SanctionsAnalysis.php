<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionsAnalysis extends Model
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
        'total_amount' => 'decimal:4',
        'analyzed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
