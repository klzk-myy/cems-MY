<?php

namespace App\Models\Compliance;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\BaseModel;
use App\Models\Traits\HasStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property FindingType $finding_type
 * @property FindingSeverity $severity
 * @property string $subject_type
 * @property int $subject_id
 * @property array|null $details
 * @property FindingStatus $status
 * @property Carbon $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ComplianceFinding extends BaseModel
{
    use HasFactory, HasStatus, SoftDeletes;

    protected $fillable = [
        'finding_type',
        'severity',
        'status',
        'subject_type',
        'subject_id',
        'details',
        'generated_at',
    ];

    protected $casts = [
        'finding_type' => FindingType::class,
        'severity' => FindingSeverity::class,
        'status' => FindingStatus::class,
        'details' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the subject of the finding (polymorphic relationship).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }

    /**
     * Get the statuses considered active for this model.
     *
     * @return array<int, FindingStatus>
     */
    protected function activeStatusValues(): array
    {
        return [
            FindingStatus::New,
            FindingStatus::Reviewed,
            FindingStatus::CaseCreated,
        ];
    }

    /**
     * Get the statuses considered open for this model.
     *
     * @return array<int, FindingStatus>
     */
    protected function openStatusValues(): array
    {
        return [
            FindingStatus::New,
            FindingStatus::Reviewed,
            FindingStatus::CaseCreated,
        ];
    }

    /**
     * Dismiss the finding with a reason.
     *
     * @throws CaseManagementException if the finding cannot be dismissed
     */
    public function dismiss(string $reason): void
    {
        if (! $this->status->canBeDismissed()) {
            throw new CaseManagementException(
                "Finding cannot be dismissed in {$this->status->label()} status"
            );
        }

        $this->status = FindingStatus::Dismissed;
        $this->save();
    }

    /**
     * Mark the finding as having a case created.
     *
     * @throws CaseManagementException if a case cannot be created from this finding
     */
    public function markCaseCreated(): void
    {
        if (! $this->status->canCreateCase()) {
            throw new CaseManagementException(
                "Case cannot be created from finding in {$this->status->label()} status"
            );
        }

        $this->status = FindingStatus::CaseCreated;
        $this->save();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpenDuplicateOf(
        Builder $query,
        string $findingType,
        string $subjectType,
        int $subjectId
    ): Builder {
        return $query->withoutGlobalScope(SoftDeletingScope::class)
            ->where('finding_type', $findingType)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', [
                FindingStatus::New->value,
                FindingStatus::Reviewed->value,
                FindingStatus::CaseCreated->value,
            ]);
    }
}
