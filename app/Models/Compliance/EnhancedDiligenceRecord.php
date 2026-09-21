<?php

namespace App\Models\Compliance;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Enums\EmploymentStatus;
use App\Models\Bases\ComplianceModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $flagged_transaction_id
 * @property int $customer_id
 * @property string $edd_reference EDD-YYYYMM-XXXX
 * @property EddStatus $status
 * @property EddRiskLevel $risk_level
 * @property string|null $source_of_funds
 * @property string|null $source_of_funds_description
 * @property string|null $purpose_of_transaction
 * @property string|null $business_justification
 * @property EmploymentStatus|null $employment_status
 * @property string|null $employer_name
 * @property string|null $employer_address
 * @property string|null $annual_income_range
 * @property string|null $estimated_net_worth
 * @property string|null $source_of_wealth
 * @property string|null $source_of_wealth_description
 * @property string|null $additional_information
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property array<mixed>|null $questionnaire_responses
 * @property Carbon|null $questionnaire_completed_at
 * @property int|null $questionnaire_completed_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EnhancedDiligenceRecord extends ComplianceModel
{
    use HasFactory;

    protected $fillable = [
        'flagged_transaction_id',
        'edd_reference',
        'risk_level',
        'source_of_funds',
        'source_of_funds_description',
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
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'questionnaire_responses',
        'questionnaire_completed_at',
        'questionnaire_completed_by',
        'approved_by',
        'approved_at',
        'customer_id',
    ];

    protected $casts = [
        'questionnaire_responses' => 'array',
        'reviewed_at' => 'datetime',
        'questionnaire_completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => EddStatus::class,
        'risk_level' => EddRiskLevel::class,
        'employment_status' => EmploymentStatus::class,
    ];

    /**
     * @return BelongsTo<FlaggedTransaction, $this>
     */
    public function flaggedTransaction(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class);
    }

    /**
     * @return HasMany<EddDocumentRequest, $this>
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(EddDocumentRequest::class, 'edd_record_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
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

    /**
     * @return BelongsTo<EddQuestionnaireTemplate, $this>
     */
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
