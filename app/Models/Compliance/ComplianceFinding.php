<?php

namespace App\Models\Compliance;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'finding_type',
        'severity',
        'subject_type',
        'subject_id',
        'details',
        'status',
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
     * Dismiss the finding with a reason.
     *
     * @throws \InvalidArgumentException if the finding cannot be dismissed
     */
    public function dismiss(string $reason): void
    {
        if (! $this->status->canBeDismissed()) {
            throw new \InvalidArgumentException(
                "Finding cannot be dismissed in {$this->status->label()} status"
            );
        }

        $this->status = FindingStatus::Dismissed;
        $this->save();
    }

    /**
     * Mark the finding as having a case created.
     *
     * @throws \InvalidArgumentException if a case cannot be created from this finding
     */
    public function markCaseCreated(): void
    {
        if (! $this->status->canCreateCase()) {
            throw new \InvalidArgumentException(
                "Case cannot be created from finding in {$this->status->label()} status"
            );
        }

        $this->status = FindingStatus::CaseCreated;
        $this->save();
    }

    /**
     * Check if the finding is in New status.
     */
    public function isNew(): bool
    {
        return $this->status === FindingStatus::New;
    }

    /**
     * Check if the finding has Critical severity.
     */
    public function isCritical(): bool
    {
        return $this->severity === FindingSeverity::Critical;
    }

    /**
     * Scope to filter findings by status.
     */
    public function scopeWithStatus($query, FindingStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Scope to filter findings by severity.
     */
    public function scopeWithSeverity($query, FindingSeverity $severity)
    {
        return $query->where('severity', $severity->value);
    }

    /**
     * Scope to filter new findings.
     */
    public function scopeNew($query)
    {
        return $query->where('status', FindingStatus::New->value);
    }

    /**
     * Scope to filter findings by type.
     */
    public function scopeOfType($query, FindingType $type)
    {
        return $query->where('finding_type', $type->value);
    }
}
