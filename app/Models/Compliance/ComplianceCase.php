<?php

namespace App\Models\Compliance;

use App\Enums\AlertPriority;
use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Enums\FlagStatus;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\Bases\ComplianceModel;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $case_number
 * @property ComplianceCaseType $case_type
 * @property ComplianceCaseStatus $status
 * @property FindingSeverity $severity
 * @property ComplianceCasePriority $priority
 * @property int|null $customer_id
 * @property int|null $primary_flag_id
 * @property int|null $primary_finding_id
 * @property int $assigned_to
 * @property string|null $case_summary
 * @property \Illuminate\Support\Carbon $sla_deadline
 * @property \Illuminate\Support\Carbon|null $escalated_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property CaseResolution|null $resolution
 * @property string|null $resolution_notes
 * @property array|null $metadata
 * @property string $created_via
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Customer|null $customer
 */
class ComplianceCase extends ComplianceModel
{
    use HasFactory, SoftDeletes;

    /**
     * Workflow-transition outputs (resolution, escalated_at) are deliberately
     * NOT mass-assignable: they are written exclusively by close()/escalate().
     * The remaining workflow fields (status, assigned_to, sla_deadline,
     * resolved_at, case_number) must stay listed because
     * CaseManagementService creates/transitions cases through mass assignment.
     */
    protected $fillable = [
        'case_number',
        'case_type',
        'severity',
        'priority',
        'primary_flag_id',
        'primary_finding_id',
        'assigned_to',
        'case_summary',
        'sla_deadline',
        'resolved_at',
        'resolution_notes',
        'metadata',
        'created_via',
        'customer_id',
        'status',
    ];

    protected $casts = [
        'case_type' => ComplianceCaseType::class,
        'status' => ComplianceCaseStatus::class,
        'severity' => FindingSeverity::class,
        'priority' => ComplianceCasePriority::class,
        'resolution' => CaseResolution::class,
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Case-number generation locks, keyed by the case instance being saved.
     * Held from the creating event until after INSERT so two concurrent
     * creations cannot compute the same sequence number.
     */
    protected static ?\WeakMap $caseNumberLocks = null;

    /**
     * Boot the model and register event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate case number on create. The generation lock is held
        // until the row is actually inserted (released in the created event)
        // so concurrent creations can never receive the same number.
        static::creating(function (ComplianceCase $case) {
            if (empty($case->case_number)) {
                $lock = static::acquireCaseNumberLock();

                try {
                    $case->case_number = static::nextCaseNumber();
                } catch (\Throwable $e) {
                    $lock->release();

                    throw $e;
                }

                static::$caseNumberLocks ??= new \WeakMap;
                static::$caseNumberLocks[$case] = $lock;
            }

            // Calculate SLA based on severity if not provided
            if (empty($case->sla_deadline)) {
                $case->sla_deadline = static::calculateSlaDeadline($case->severity);
            }
        });

        static::created(function (ComplianceCase $case) {
            static::releaseCaseNumberLock($case);
        });
    }

    /**
     * Cache-lock name serializing case-number generation for a given year.
     */
    protected static function caseNumberLockName(): string
    {
        return 'compliance-case-number:'.now()->format('Y');
    }

    /**
     * Acquire the case-number generation lock with bounded retries.
     *
     * @throws CaseManagementException when the lock cannot be acquired
     */
    protected static function acquireCaseNumberLock(): Lock
    {
        $maxAttempts = 3;
        $lock = Cache::lock(static::caseNumberLockName(), 15);

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                if ($lock->block(5)) {
                    return $lock;
                }
            }
        } catch (LockTimeoutException $e) {
            throw new CaseManagementException(
                "Failed to acquire case number generation lock after {$maxAttempts} attempts",
                0,
                $e
            );
        }

        throw new CaseManagementException('Failed to acquire case number generation lock');
    }

    /**
     * Release the per-instance lock held across insert, if any. Locks whose
     * owner died before this point expire via their 15 second TTL.
     */
    protected static function releaseCaseNumberLock(ComplianceCase $case): void
    {
        if (static::$caseNumberLocks !== null && isset(static::$caseNumberLocks[$case])) {
            $lock = static::$caseNumberLocks[$case];
            unset(static::$caseNumberLocks[$case]);
            $lock->release();
        }
    }

    /**
     * Generate a unique case number in format CASE-YYYY-NNNNN.
     *
     * Public entry point for callers that generate the number before creating
     * the case. Generation is serialized by a cache lock; uniqueness probes
     * include soft-deleted rows because they still occupy their numbers under
     * the unique index on case_number.
     */
    public static function generateCaseNumber(): string
    {
        $lock = static::acquireCaseNumberLock();

        try {
            return static::nextCaseNumber();
        } finally {
            $lock->release();
        }
    }

    /**
     * Compute the next free sequence for the current year. Callers must hold
     * the generation lock.
     */
    protected static function nextCaseNumber(): string
    {
        $year = now()->year;
        $prefix = "CASE-{$year}-";

        // Soft-deleted cases still occupy their numbers, so both the max probe
        // and the existence checks must see them (withTrashed()).
        $latest = static::withTrashed()
            ->where('case_number', 'like', "{$prefix}%")
            ->orderBy('case_number', 'desc')
            ->first();

        $nextSequence = $latest ? ((int) substr($latest->case_number, -5)) + 1 : 1;

        // Bounded retry: skip any number taken between the max probe above and
        // this moment (e.g. by an external generateCaseNumber() caller).
        while ($nextSequence <= 99999 && static::withTrashed()
            ->where('case_number', $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT))
            ->exists()) {
            $nextSequence++;
        }

        if ($nextSequence > 99999) {
            throw new CaseManagementException("No available case numbers for {$year}");
        }

        return $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * SLA hours for a finding severity. Single source of truth shared with
     * CaseManagementService so every case-creation path applies the same policy.
     */
    public static function slaHoursFor(FindingSeverity $severity): int
    {
        return match ($severity) {
            FindingSeverity::Critical => 24,
            FindingSeverity::High => 48,
            FindingSeverity::Medium => 120,
            FindingSeverity::Low => 240,
        };
    }

    /**
     * Calculate SLA deadline based on severity.
     */
    public static function calculateSlaDeadline(FindingSeverity $severity): Carbon
    {
        return now()->addHours(static::slaHoursFor($severity));
    }

    /**
     * Add a note to this case.
     */
    public function addNote(int $authorId, CaseNoteType $noteType, string $content, bool $isInternal = true): ComplianceCaseNote
    {
        $note = $this->notes()->create([
            'author_id' => $authorId,
            'note_type' => $noteType,
            'content' => $content,
            'is_internal' => $isInternal,
        ]);

        assert($note instanceof ComplianceCaseNote);

        return $note;
    }

    /**
     * Assign this case to a compliance officer.
     */
    public function assignTo(int $officerId): void
    {
        $this->assigned_to = max(0, $officerId);
        $this->save();
    }

    /**
     * Close this case with resolution.
     */
    public function close(CaseResolution $resolution, ?string $notes = null): void
    {
        $this->status = ComplianceCaseStatus::Closed;
        $this->resolution = $resolution;
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
        $link = $this->links()->create([
            'linked_type' => $type,
            'linked_id' => $id,
            'created_at' => now(),
        ]);

        assert($link instanceof ComplianceCaseLink);

        return $link;
    }

    /**
     * Get the customer this case is raised against.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the primary flagged transaction.
     *
     * @return BelongsTo<FlaggedTransaction, $this>
     */
    public function primaryFlag(): BelongsTo
    {
        return $this->belongsTo(FlaggedTransaction::class, 'primary_flag_id');
    }

    /**
     * Get the primary compliance finding.
     *
     * @return BelongsTo<ComplianceFinding, $this>
     */
    public function primaryFinding(): BelongsTo
    {
        return $this->belongsTo(ComplianceFinding::class, 'primary_finding_id');
    }

    /**
     * Get the compliance officer assigned to this case.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the notes for this case.
     *
     * @return HasMany<ComplianceCaseNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ComplianceCaseNote::class, 'case_id');
    }

    /**
     * Get the documents for this case.
     *
     * @return HasMany<ComplianceCaseDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceCaseDocument::class, 'case_id');
    }

    /**
     * Get the links for this case.
     *
     * @return HasMany<ComplianceCaseLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ComplianceCaseLink::class, 'case_id');
    }

    /**
     * Get the statuses considered active for this model.
     *
     * @return array<int, ComplianceCaseStatus>
     */
    protected function activeStatusValues(): array
    {
        return [
            ComplianceCaseStatus::Open,
            ComplianceCaseStatus::UnderReview,
            ComplianceCaseStatus::PendingApproval,
            ComplianceCaseStatus::Escalated,
        ];
    }

    /**
     * Get the statuses considered open for this model.
     *
     * @return array<int, ComplianceCaseStatus>
     */
    protected function openStatusValues(): array
    {
        return [
            ComplianceCaseStatus::Open,
            ComplianceCaseStatus::UnderReview,
            ComplianceCaseStatus::PendingApproval,
            ComplianceCaseStatus::Escalated,
        ];
    }

    /**
     * Scope: Filter cases under review.
     */
    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', ComplianceCaseStatus::UnderReview->value);
    }

    /**
     * Scope: Filter cases by assignee.
     */
    public function scopeByAssignee(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope: Filter overdue cases.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('sla_deadline', '<', now())
            ->where('status', '!=', ComplianceCaseStatus::Closed->value);
    }

    /**
     * Get alerts linked to this case.
     */
    /**
     * @return HasMany<Alert, $this>
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

        // All linked alerts must be resolved or rejected (UnifiedAlertController
        // writes exactly these values when alerts are closed out).
        $unresolvedAlerts = $this->alerts()
            ->whereNotIn('status', [FlagStatus::Resolved->value, FlagStatus::Rejected->value])
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
            ->map(fn (Alert $alert) => $alert->priority)
            ->filter()
            ->values();

        if ($alertPriorities->isEmpty()) {
            return ComplianceCasePriority::Medium;
        }

        // Map by backing value: AlertPriority uses lowercase values while
        // ComplianceCasePriority uses TitleCase, and enum instances cannot be
        // used as array keys (TypeError).
        $priorityOrder = [
            AlertPriority::Critical->value => 1,
            AlertPriority::High->value => 2,
            AlertPriority::Medium->value => 3,
            AlertPriority::Low->value => 4,
        ];

        /** @var AlertPriority $highest */
        $highest = $alertPriorities
            ->sortBy(fn (AlertPriority $priority) => $priorityOrder[$priority->value])
            ->first();

        return match ($highest) {
            AlertPriority::Critical => ComplianceCasePriority::Critical,
            AlertPriority::High => ComplianceCasePriority::High,
            AlertPriority::Medium => ComplianceCasePriority::Medium,
            AlertPriority::Low => ComplianceCasePriority::Low,
        };
    }
}
