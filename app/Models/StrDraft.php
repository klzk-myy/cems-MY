<?php

namespace App\Models;

use App\Enums\StrStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'alert_ids',
        'customer_id',
        'transaction_ids',
        'narrative',
        'suspected_activity',
        'confidence_score',
        'filing_deadline',
        'status',
        'converted_to_str_id',
        'created_by',
    ];

    protected $casts = [
        'alert_ids' => 'array',
        'transaction_ids' => 'array',
        'confidence_score' => 'integer',
        'filing_deadline' => 'date',
        'status' => StrStatus::class,
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Compliance\ComplianceCase::class, 'case_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedToStr(): BelongsTo
    {
        return $this->belongsTo(StrReport::class, 'converted_to_str_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_to_str_id !== null;
    }

    public function canConvert(): bool
    {
        return ! $this->isConverted()
            && $this->confidence_score >= 80
            && $this->filing_deadline !== null
            && now()->diffInHours($this->filing_deadline) <= 48;
    }

    public function isOverdue(): bool
    {
        return $this->filing_deadline !== null
            && now()->isAfter($this->filing_deadline);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            StrStatus::Draft,
            StrStatus::PendingReview,
            StrStatus::PendingApproval,
        ]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('filing_deadline', '<', now())
            ->whereNull('converted_to_str_id');
    }
}
