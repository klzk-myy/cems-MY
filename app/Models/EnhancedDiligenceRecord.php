<?php

namespace App\Models;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Enums\EmploymentStatus;
use App\Models\Bases\ComplianceModel;
use App\Models\Compliance\EddQuestionnaireTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnhancedDiligenceRecord extends ComplianceModel
{
    use HasFactory;

    protected $with = ['customer', 'reviewer', 'template'];

    protected $fillable = [
        'flagged_transaction_id',
        'edd_reference',
        'edd_template_id',
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
        'questionnaire_completed_at',
        'questionnaire_completed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'source_of_funds_documents' => 'array',
        'supporting_documents' => 'array',
        'questionnaire_responses' => 'array',
        'responses' => 'array',
        'documents_received' => 'array',
        'reviewed_at' => 'datetime',
        'questionnaire_completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => EddStatus::class,
        'risk_level' => EddRiskLevel::class,
        'employment_status' => EmploymentStatus::class,
    ];

    public function flaggedTransaction(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function questionnaireCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'questionnaire_completed_by');
    }

    /**
     * Get the statuses considered active for this model.
     *
     * @return array<int, EddStatus>
     */
    protected function activeStatusValues(): array
    {
        return [
            EddStatus::Incomplete,
            EddStatus::PendingQuestionnaire,
            EddStatus::QuestionnaireSubmitted,
            EddStatus::PendingReview,
        ];
    }

    /**
     * Get the statuses considered open for this model.
     *
     * @return array<int, EddStatus>
     */
    protected function openStatusValues(): array
    {
        return [
            EddStatus::Incomplete,
            EddStatus::PendingQuestionnaire,
            EddStatus::QuestionnaireSubmitted,
            EddStatus::PendingReview,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EddQuestionnaireTemplate::class, 'edd_template_id');
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
