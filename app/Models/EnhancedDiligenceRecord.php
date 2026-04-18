<?php

namespace App\Models;

use App\Enums\EddStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnhancedDiligenceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'flagged_transaction_id',
        'customer_id',
        'edd_reference',
        'edd_template_id',
        'status',
        'risk_level',
        'source_of_funds',
        'source_of_funds_description',
        'source_of_funds_documents',
        'purpose_of_transaction',
        'business_justification',
        'employment_status',
        'employer_name',
        'employer_address',
        'annual_income_range',
        'estimated_net_worth',
        'source_of_wealth',
        'source_of_wealth_description',
        'additional_information',
        'supporting_documents',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'questionnaire_responses',
    ];

    protected $casts = [
        'source_of_funds_documents' => 'array',
        'supporting_documents' => 'array',
        'questionnaire_responses' => 'array',
        'reviewed_at' => 'datetime',
        'status' => EddStatus::class,
        'risk_level' => 'string',
    ];

    public function flaggedTransaction(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Compliance\EddQuestionnaireTemplate::class, 'edd_template_id');
    }

    public function isComplete(): bool
    {
        return $this->status !== EddStatus::Incomplete;
    }

    public function isPendingReview(): bool
    {
        return $this->status === EddStatus::PendingReview;
    }

    public function isApproved(): bool
    {
        return $this->status === EddStatus::Approved;
    }
}
