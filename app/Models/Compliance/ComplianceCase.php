<?php

namespace App\Models\Compliance;

use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Enums\FlagStatus;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\StrDraft;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_number',
        'case_type',
        'status',
        'severity',
        'priority',
        'customer_id',
        'primary_flag_id',
        'primary_finding_id',
        'assigned_to',
        'case_summary',
        'sla_deadline',
        'escalated_at',
        'resolved_at',
        'resolution',
        'resolution_notes',
        'metadata',
        'created_via',
    ];

    protected $casts = [
        'case_type' => ComplianceCaseType::class,
        'status' => ComplianceCaseStatus::class,
        'severity' => FindingSeverity::class,
        'priority' => ComplianceCasePriority::class,
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Boot the model and register event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate case number on create
        static::creating(function (ComplianceCase $case) {
            if (empty($case->case_number)) {
                $case->case_number = static::generateCaseNumber();
            }

            // Calculate SLA based on severity if not provided
            if (empty($case->sla_deadline)) {
                $case->sla_deadline = static::calculateSlaDeadline($case->severity);
            }
        });
    }

    /**
     * Generate a unique case number in format CASE-YYYY-NNNNN.
     */
    public static function generateCaseNumber(): string
    {
        $year = date('Y');
        $prefix = "CASE-{$year}-";

        // Get the latest case number for this year
        $latestCase = static::where('case_number', 'like', "{$prefix}%")
            ->orderBy('case_number', 'desc')
            ->first();

        if ($latestCase) {
            // Extract the sequence number and increment
            $lastSequence = (int) substr($latestCase->case_number, -5);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.str_pad($nextSequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate SLA deadline based on severity.
     */
    public static function calculateSlaDeadline(FindingSeverity $severity): Carbon
    {
        $hours = match ($severity) {
            FindingSeverity::Critical => 24,
            FindingSeverity::High => 48,
            FindingSeverity::Medium => 72,
            FindingSeverity::Low => 120,
        };

        return now()->addHours($hours);
    }

    /**
     * Add a note to this case.
     */
    public function addNote(int $authorId, CaseNoteType $noteType, string $content, bool $isInternal = true): ComplianceCaseNote
    {
        return $this->notes()->create([
            'author_id' => $authorId,
            'note_type' => $noteType,
            'content' => $content,
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Assign this case to a compliance officer.
     */
    public function assignTo(int $officerId): void
    {
        $this->assigned_to = $officerId;
        $this->save();
    }

    /**
     * Close this case with resolution.
     */
    public function close(CaseResolution $resolution, ?string $notes = null): void
    {
        $this->status = ComplianceCaseStatus::Closed;
        $this->resolution = $resolution->value;
        $this->resolution_notes = $notes;
        $this->resolved_at = now();
        $this->save();
    }

    /**
     * Escalate this case.
     */
    public function escalate(): void
    {
        $this->status = ComplianceCaseStatus::Escalated;
        $this->escalated_at = now();
        $this->save();
    }

    /**
     * Add a link to this case.
     */
    public function addLink(string $type, int $id): ComplianceCaseLink
    {
        return $this->links()->create([
            'linked_type' => $type,
            'linked_id' => $id,
            'created_at' => now(),
        ]);
    }

    /**
     * Get the customer associated with this case.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the primary flagged transaction.
     */
    public function primaryFlag(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class, 'primary_flag_id');
    }

    /**
     * Get the primary compliance finding.
     */
    public function primaryFinding(): BelongsTo
    {
        return $this->belongsTo(ComplianceFinding::class, 'primary_finding_id');
    }

    /**
     * Get the compliance officer assigned to this case.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the notes for this case.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ComplianceCaseNote::class, 'case_id');
    }

    /**
     * Get the documents for this case.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceCaseDocument::class, 'case_id');
    }

    /**
     * Get the links for this case.
     */
    public function links(): HasMany
    {
        return $this->hasMany(ComplianceCaseLink::class, 'case_id');
    }

    /**
     * Get the linked subject (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'linked_type', 'linked_id');
    }

    /**
     * Scope: Filter open cases.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', '!=', ComplianceCaseStatus::Closed->value);
    }

    /**
     * Scope: Filter cases under review.
     */
    public function scopeUnderReview($query)
    {
        return $query->where('status', ComplianceCaseStatus::UnderReview->value);
    }

    /**
     * Scope: Filter active cases (not closed).
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', ComplianceCaseStatus::Closed->value);
    }

    /**
     * Scope: Filter cases by assignee.
     */
    public function scopeByAssignee($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope: Filter overdue cases.
     */
    public function scopeOverdue($query)
    {
        return $query->where('sla_deadline', '<', now())
            ->where('status', '!=', ComplianceCaseStatus::Closed->value);
    }

    /**
     * Get STR drafts linked to this case.
     */
    public function strDrafts(): HasMany
    {
        return $this->hasMany(StrDraft::class, 'case_id');
    }

    /**
     * Get alerts linked to this case.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'case_id');
    }

    /**
     * Check if the case can be resolved.
     * All linked alerts must be resolved before the case can be resolved.
     */
    public function canBeResolved(): bool
    {
        // Case must not be already closed
        if ($this->status === ComplianceCaseStatus::Closed) {
            return false;
        }

        // All linked alerts must be resolved
        $unresolvedAlerts = $this->alerts()
            ->whereNotIn('status', [FlagStatus::Resolved, FlagStatus::Dismissed])
            ->count();

        return $unresolvedAlerts === 0;
    }

    /**
     * Derive case priority from linked alerts.
     * Returns the highest priority among all linked alerts.
     */
    public function derivePriorityFromAlerts(): ComplianceCasePriority
    {
        $alertPriorities = $this->alerts()
            ->get()
            ->map(fn ($alert) => $alert->priority)
            ->filter();

        if ($alertPriorities->isEmpty()) {
            return ComplianceCasePriority::Medium;
        }

        $priorityOrder = [
            ComplianceCasePriority::Critical => 1,
            ComplianceCasePriority::High => 2,
            ComplianceCasePriority::Medium => 3,
            ComplianceCasePriority::Low => 4,
        ];

        return $alertPriorities->sort(fn ($a, $b) => ($priorityOrder[$a] ?? 99) <=> ($priorityOrder[$b] ?? 99)
        )->first();
    }
}
