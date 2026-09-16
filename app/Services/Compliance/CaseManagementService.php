<?php

namespace App\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Enums\FlagStatus;
use App\Events\CaseOpened;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\Compliance\ComplianceFinding;
use App\Models\User;
use App\Notifications\ComplianceCaseAssignedNotification;
use App\Notifications\ComplianceCaseSlaBreachedNotification;
use App\Services\AuditService;
use App\Services\System\SystemAlertService;
use App\Support\ActorContext;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing compliance cases and their lifecycle.
 * Handles case creation, assignment, notes, and resolution.
 */
class CaseManagementService
{
    /**
     * Storage extension allowlist for case documents.
     * Mirrors UploadCaseDocumentRequest validation.
     */
    private const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public function __construct(
        protected SystemAlertService $alertService,
        protected AuditService $auditService,
        protected StrReportService $strReportService,
    ) {}

    /**
     * Create a compliance case from a finding.
     */
    public function createCaseFromFinding(
        ComplianceFinding $finding,
        ComplianceCaseType $caseType,
        int $assignedTo,
        ?string $summary = null
    ): ComplianceCase {
        return DB::transaction(function () use ($finding, $caseType, $assignedTo, $summary) {
            $case = ComplianceCase::create([
                'case_type' => $caseType,
                'status' => ComplianceCaseStatus::Open,
                'severity' => $finding->severity,
                'priority' => $this->severityToPriority($finding->severity),
                'customer_id' => class_basename($finding->subject_type) === 'Customer' ? $finding->subject_id : null,
                'primary_finding_id' => $finding->id,
                'assigned_to' => $assignedTo,
                'case_summary' => $summary,
                'sla_deadline' => $this->calculateSlaDeadline($finding->severity, $caseType),
                'created_via' => 'Automated',
            ]);

            $finding->markCaseCreated();

            $this->auditService->logWithSeverity(
                'compliance_case_created',
                [
                    'description' => "Compliance case {$case->case_number} created from finding #{$finding->id}",
                    'case_id' => $case->id,
                    'customer_id' => $case->customer_id,
                    'finding_id' => $finding->id,
                ],
                'INFO'
            );

            return $case;
        });
    }

    /**
     * Create a manual compliance case.
     */
    public function createManualCase(
        ComplianceCaseType $caseType,
        int $customerId,
        int $assignedTo,
        FindingSeverity $severity,
        ?string $summary = null,
        ?int $primaryFlagId = null
    ): ComplianceCase {
        return ComplianceCase::create([
            'case_type' => $caseType,
            'status' => ComplianceCaseStatus::Open,
            'severity' => $severity,
            'priority' => $this->severityToPriority($severity),
            'customer_id' => $customerId,
            'primary_flag_id' => $primaryFlagId,
            'assigned_to' => $assignedTo,
            'case_summary' => $summary,
            'sla_deadline' => $this->calculateSlaDeadline($severity, $caseType),
            'created_via' => 'Manual',
        ]);
    }

    /**
     * Add a note to a case.
     */
    public function addNote(
        ComplianceCase $case,
        int $authorId,
        CaseNoteType $noteType,
        string $content,
        bool $isInternal = true
    ): ComplianceCaseNote {
        return ComplianceCaseNote::create([
            'case_id' => $case->id,
            'author_id' => $authorId,
            'note_type' => $noteType,
            'content' => $content,
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Emit the canonical `compliance_case_assigned` audit record.
     */
    protected function auditCaseAssigned(ComplianceCase $case, ?int $previousAssignee, int $assigneeId): void
    {
        $this->auditService->logWithSeverity(
            'compliance_case_assigned',
            [
                'description' => "Compliance case {$case->case_number} assigned to officer #{$assigneeId}",
                'case_id' => $case->id,
                'previous_assignee' => $previousAssignee,
                'assigned_to' => $assigneeId,
            ],
            'INFO'
        );
    }

    /**
     * Notify the newly-assigned officer of the case, including SLA runway.
     */
    protected function notifyAssignee(ComplianceCase $case, int $assigneeId): void
    {
        try {
            $assignee = User::find($assigneeId);

            if (! $assignee) {
                return;
            }

            $assignee->notify(new ComplianceCaseAssignedNotification(
                $case,
                ActorContext::capture()->user
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify case assignee', [
                'case_id' => $case->id,
                'assignee_id' => $assigneeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Close a case with a resolution.
     */
    public function closeCase(
        ComplianceCase $case,
        CaseResolution $resolution,
        ?string $notes = null
    ): ComplianceCase {
        if ($case->status === ComplianceCaseStatus::Closed) {
            throw new CaseManagementException("Cannot close case {$case->id}: already closed");
        }

        return $this->transitionTo(
            $case,
            ComplianceCaseStatus::Closed,
            fn (ComplianceCase $case) => $case->update([
                'resolution' => $resolution,
                'resolution_notes' => $notes,
            ]),
            [
                'action' => 'compliance_case_closed',
                'data' => [
                    'description' => "Compliance case {$case->case_number} closed ({$resolution->value})",
                    'case_id' => $case->id,
                    'resolution' => $resolution->value,
                    'notes' => $notes,
                ],
            ],
        );
    }

    /**
     * Escalate a case.
     */
    public function escalateCase(ComplianceCase $case): ComplianceCase
    {
        return $this->transitionTo(
            $case,
            ComplianceCaseStatus::Escalated,
            fn (ComplianceCase $case) => $case->update(['escalated_at' => now()]),
            [
                'action' => 'compliance_case_escalated',
                'data' => [
                    'description' => "Compliance case {$case->case_number} escalated",
                    'case_id' => $case->id,
                ],
                'severity' => 'WARNING',
            ],
        );
    }

    /**
     * Calculate SLA deadline based on severity and case type.
     *
     * Hours come from ComplianceCase::slaHoursFor() (single source of truth);
     * urgent case types are capped at 24h. Previously the cap referenced a
     * non-existent ComplianceCaseType::Str case, which threw an Error on every
     * call and made case creation via the API fail.
     */
    protected function calculateSlaDeadline(FindingSeverity $severity, ComplianceCaseType $caseType): Carbon
    {
        $hours = ComplianceCase::slaHoursFor($severity);

        if ($caseType === ComplianceCaseType::SanctionReview || $caseType === ComplianceCaseType::Counterfeit) {
            $hours = min($hours, 24);
        }

        return now()->addHours($hours);
    }

    /**
     * Convert severity to priority.
     */
    protected function severityToPriority(FindingSeverity $severity): ComplianceCasePriority
    {
        return match ($severity) {
            FindingSeverity::Critical => ComplianceCasePriority::Critical,
            FindingSeverity::High => ComplianceCasePriority::High,
            FindingSeverity::Medium => ComplianceCasePriority::Medium,
            FindingSeverity::Low => ComplianceCasePriority::Low,
        };
    }

    /**
     * Create a case from one or more alerts.
     *
     * @throws CaseManagementException when alerts span multiple customers
     */
    public function createFromAlerts(array $alertIds, int $openedBy): ComplianceCase
    {
        return DB::transaction(function () use ($alertIds, $openedBy) {
            $alerts = Alert::whereIn('id', $alertIds)->get();

            if ($alerts->isEmpty()) {
                throw new CaseManagementException('No alerts provided');
            }

            $customerIds = $alerts->pluck('customer_id')->unique()->filter();

            if ($customerIds->count() > 1) {
                throw new CaseManagementException('Alerts belong to multiple customers; cannot create a single case');
            }

            $priority = AlertPriority::fromRiskScore($alerts->max('risk_score'));
            $casePriority = $this->casePriorityForAlertPriority($priority);

            // Every NOT NULL column must be populated: case_type, severity,
            // assigned_to and created_via previously went missing (and a
            // non-existent 'opened_by' column was written), so the insert always
            // failed. The case is assigned to the officer who opened it.
            $case = ComplianceCase::create([
                'case_number' => ComplianceCase::generateCaseNumber(),
                'case_type' => ComplianceCaseType::Investigation,
                'status' => ComplianceCaseStatus::Open,
                'severity' => $this->severityForPriority($priority),
                'priority' => $casePriority,
                'customer_id' => $customerIds->first(),
                'assigned_to' => $openedBy,
                'created_via' => 'Manual',
                'sla_deadline' => $this->calculateSlaDeadlineFromPriority($casePriority),
            ]);

            foreach ($alerts as $alert) {
                $alert->update(['case_id' => $case->id]);
            }

            $this->auditService->logWithSeverity(
                'compliance_case_created',
                [
                    'description' => "Compliance case {$case->case_number} created from alerts",
                    'case_id' => $case->id,
                    'customer_id' => $case->customer_id,
                    'alert_ids' => $alerts->pluck('id')->all(),
                    'opened_by' => $openedBy,
                ],
                'INFO'
            );

            event(new CaseOpened($case));

            return $case->load('alerts');
        });
    }

    /**
     * Link an alert to an existing case.
     *
     * @throws CaseManagementException when the alert belongs to another case or customer
     */
    public function linkAlertToCase(Alert $alert, ComplianceCase $case): Alert
    {
        if ($alert->case_id && $alert->case_id !== $case->id) {
            throw new CaseManagementException('Alert already linked to another case');
        }

        if ($alert->customer_id && $case->customer_id && $alert->customer_id !== $case->customer_id) {
            throw new CaseManagementException('Alert belongs to a different customer than the case');
        }

        return DB::transaction(function () use ($alert, $case) {
            $alert->update(['case_id' => $case->id]);
            $this->recalculateCasePriority($case);
            $this->recalculateCaseSla($case);

            $this->auditService->logWithSeverity(
                'compliance_case_alerts_linked',
                [
                    'description' => "Alert #{$alert->id} linked to compliance case {$case->case_number}",
                    'case_id' => $case->id,
                    'alert_ids' => [$alert->id],
                ],
                'INFO'
            );

            return $alert->fresh();
        });
    }

    /**
     * Merge two cases together.
     *
     * @throws CaseManagementException for self-merges, closed targets or
     *                                 cases belonging to different customers
     */
    public function mergeCases(ComplianceCase $sourceCase, ComplianceCase $targetCase): ComplianceCase
    {
        if ($sourceCase->is($targetCase)) {
            throw new CaseManagementException('Cannot merge a case into itself');
        }

        if ($targetCase->status === ComplianceCaseStatus::Closed) {
            throw new CaseManagementException('Cannot merge into a closed case');
        }

        if ($sourceCase->customer_id !== $targetCase->customer_id) {
            throw new CaseManagementException('Cannot merge cases for different customers');
        }

        return DB::transaction(function () use ($sourceCase, $targetCase) {
            Alert::where('case_id', $sourceCase->id)
                ->update(['case_id' => $targetCase->id]);

            // Move evidence with the case so it stays visible on the target.
            ComplianceCaseDocument::where('case_id', $sourceCase->id)
                ->update(['case_id' => $targetCase->id]);
            ComplianceCaseLink::where('case_id', $sourceCase->id)
                ->update(['case_id' => $targetCase->id]);

            // The merge audit record doubles as the source case's close audit.
            $this->transitionTo($sourceCase, ComplianceCaseStatus::Closed, fn () => null, [
                'action' => 'compliance_case_merged',
                'data' => [
                    'description' => "Compliance case {$sourceCase->case_number} merged into {$targetCase->case_number}",
                    'source_case_id' => $sourceCase->id,
                    'target_case_id' => $targetCase->id,
                ],
            ]);

            $this->recalculateCasePriority($targetCase);
            $this->recalculateCaseSla($targetCase);

            return $targetCase->fresh()->load(['alerts', 'documents', 'links']);
        });
    }

    /**
     * Update case status, enforcing the model's allowed transitions.
     *
     * @throws CaseManagementException when the transition is not allowed
     */
    public function updateStatus(ComplianceCase $case, ComplianceCaseStatus $status): ComplianceCase
    {
        $closing = $status === ComplianceCaseStatus::Closed;

        return $this->transitionTo($case, $status, fn () => null, [
            'action' => $closing ? 'compliance_case_closed' : 'compliance_case_status_changed',
            'data' => $closing
                ? [
                    'description' => "Compliance case {$case->case_number} closed via status update",
                    'case_id' => $case->id,
                ]
                : [
                    'description' => "Compliance case {$case->case_number} moved from {$case->status->value} to {$status->value}",
                    'case_id' => $case->id,
                    'from_status' => $case->status->value,
                    'to_status' => $status->value,
                ],
        ]);
    }

    /**
     * Assign case to an officer.
     */
    public function assignToOfficer(ComplianceCase $case, int $userId): ComplianceCase
    {
        $previousAssignee = $case->assigned_to;

        return DB::transaction(function () use ($case, $userId, $previousAssignee) {
            $case->update(['assigned_to' => $userId]);

            if ($case->status === ComplianceCaseStatus::Open) {
                $case->update(['status' => ComplianceCaseStatus::UnderReview]);
            }

            if ($previousAssignee !== $userId) {
                $this->notifyAssignee($case, $userId);
            }

            $this->auditCaseAssigned($case, $previousAssignee, $userId);

            return $case->fresh();
        });
    }

    /**
     * Resolve a case (requires all alerts to be resolved).
     */
    public function resolveCase(ComplianceCase $case, int $resolvedBy, ?string $notes = null): ComplianceCase
    {
        if (! $case->canBeResolved()) {
            throw new CaseManagementException('Cannot resolve case: not all alerts are linked');
        }

        return $this->transitionTo($case, ComplianceCaseStatus::Closed, fn () => null, [
            'action' => 'compliance_case_closed',
            'data' => [
                'description' => "Compliance case {$case->case_number} resolved by user #{$resolvedBy}",
                'case_id' => $case->id,
                'resolved_by' => $resolvedBy,
                'notes' => $notes,
            ],
        ]);
    }

    /**
     * Single transition pipeline for case status changes. Enforces the
     * enum's allowed transitions, applies caller-specific mutations inside
     * one transaction, stamps resolved_at on closure, writes the audit
     * record, and fires the STR auto-draft for every close path.
     *
     * @param  array{action: string, data: array<string, mixed>, severity?: string}  $audit
     *
     * @throws CaseManagementException when the transition is not allowed or
     *                                 a close is attempted with unresolved alerts
     */
    protected function transitionTo(
        ComplianceCase $case,
        ComplianceCaseStatus $status,
        Closure $mutate,
        array $audit,
    ): ComplianceCase {
        $current = $case->status;

        // Submitting the current status is a no-op, not an error.
        if ($status === $current) {
            return $case;
        }

        if (! $current->canMoveTo($status)) {
            throw new CaseManagementException(
                "Cannot move case from {$current->value} to {$status->value}"
            );
        }

        if ($status === ComplianceCaseStatus::Closed) {
            $this->assertAllAlertsResolved($case);
        }

        DB::transaction(function () use ($case, $status, $mutate, $audit) {
            $case->update(['status' => $status]);
            $mutate($case);

            if ($status === ComplianceCaseStatus::Closed && $case->resolved_at === null) {
                $case->update(['resolved_at' => now()]);
            }

            $this->auditService->logWithSeverity(
                $audit['action'],
                $audit['data'],
                $audit['severity'] ?? 'INFO'
            );
        });

        if ($status === ComplianceCaseStatus::Closed) {
            $this->autoDraftStrForClosedCase($case);
        }

        return $case->fresh();
    }

    /**
     * Closing must satisfy the same requirements as resolveCase(): every
     * linked alert must be resolved/rejected first.
     *
     * @throws CaseManagementException
     */
    protected function assertAllAlertsResolved(ComplianceCase $case): void
    {
        $unresolvedAlertIds = $case->alerts()
            ->whereNotIn('status', [FlagStatus::Resolved->value, FlagStatus::Rejected->value])
            ->pluck('id')
            ->all();

        if ($unresolvedAlertIds !== []) {
            throw new CaseManagementException(
                'Cannot close case '.$case->id.': unresolved alerts ('.implode(', ', $unresolvedAlertIds).')'
            );
        }
    }

    /**
     * Best-effort STR auto-draft for a newly Closed case. Never blocks the
     * closure: StrReportService guards threshold/duplicates internally.
     */
    protected function autoDraftStrForClosedCase(ComplianceCase $case): void
    {
        try {
            $this->strReportService->autoDraftForClosedCase($case);
        } catch (\Throwable $e) {
            Log::error('STR auto-draft failed', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate SLA deadline based on priority (single source: ComplianceCasePriority::slaHours()).
     */
    protected function calculateSlaDeadlineFromPriority(ComplianceCasePriority $priority): Carbon
    {
        return now()->addHours($priority->slaHours());
    }

    /**
     * Map an alert priority to the matching case severity.
     */
    protected function severityForPriority(AlertPriority $priority): FindingSeverity
    {
        return match ($priority) {
            AlertPriority::Critical => FindingSeverity::Critical,
            AlertPriority::High => FindingSeverity::High,
            AlertPriority::Medium => FindingSeverity::Medium,
            AlertPriority::Low => FindingSeverity::Low,
        };
    }

    /**
     * Map an alert priority to a case priority.
     *
     * The two enums share display names but differ in backing values (lowercase
     * vs TitleCase); storing the raw AlertPriority value in the case's priority
     * column made every read throw ValueError in the enum cast.
     */
    protected function casePriorityForAlertPriority(AlertPriority $priority): ComplianceCasePriority
    {
        return match ($priority) {
            AlertPriority::Critical => ComplianceCasePriority::Critical,
            AlertPriority::High => ComplianceCasePriority::High,
            AlertPriority::Medium => ComplianceCasePriority::Medium,
            AlertPriority::Low => ComplianceCasePriority::Low,
        };
    }

    /**
     * Recalculate case priority based on linked alerts.
     */
    protected function recalculateCasePriority(ComplianceCase $case): void
    {
        $priority = $case->derivePriorityFromAlerts();
        $case->update(['priority' => $priority]);
    }

    /**
     * Recalculate case SLA based on priority.
     */
    protected function recalculateCaseSla(ComplianceCase $case): void
    {
        $slaDeadline = $this->calculateSlaDeadlineFromPriority($case->priority);
        $case->update(['sla_deadline' => $slaDeadline]);
    }

    /**
     * Get open cases ordered by priority.
     */
    public function getOpenCases(): Collection
    {
        return ComplianceCase::with(['customer', 'assignee', 'alerts'])
            ->open()
            ->orderByRaw("CASE priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END")
            ->orderBy('sla_deadline')
            ->get();
    }

    /**
     * Get case summary statistics.
     */
    public function getCaseSummary(): array
    {
        return [
            'total_open' => ComplianceCase::open()->count(),
            'critical' => ComplianceCase::open()
                ->where('priority', ComplianceCasePriority::Critical)->count(),
            'high' => ComplianceCase::open()
                ->where('priority', ComplianceCasePriority::High)->count(),
            'medium' => ComplianceCase::open()
                ->where('priority', ComplianceCasePriority::Medium)->count(),
            'low' => ComplianceCase::open()
                ->where('priority', ComplianceCasePriority::Low)->count(),
            'overdue' => ComplianceCase::open()
                ->where('sla_deadline', '<', now())->count(),
            'pending_review' => ComplianceCase::where('status', ComplianceCaseStatus::PendingApproval)->count(),
        ];
    }

    /**
     * Find potential duplicate cases for a customer.
     */
    public function findPotentialDuplicates(int $customerId, ?int $excludeCaseId = null): Collection
    {
        $query = ComplianceCase::where('customer_id', $customerId)
            ->open()
            ->where('created_at', '>=', now()->subDays(7));

        if ($excludeCaseId) {
            $query->where('id', '!=', $excludeCaseId);
        }

        return $query->get();
    }

    /**
     * Add a document to a case.
     */
    public function addDocument(
        int $caseId,
        UploadedFile $file,
        int $uploadedBy
    ): ComplianceCaseDocument {
        $case = ComplianceCase::findOrFail($caseId);

        // Never trust the client-supplied filename: it can contain traversal
        // sequences that escape the case directory via storeAs. Store under a
        // generated UUID with a vetted extension and keep the original name
        // only in the database. The MIME type is derived server-side because
        // getClientMimeType() reflects the spoofable Content-Type header.
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_DOCUMENT_EXTENSIONS, true)) {
            throw new CaseManagementException(
                "Unsupported document type: '{$extension}'. Allowed types: "
                .implode(', ', self::ALLOWED_DOCUMENT_EXTENSIONS).'.'
            );
        }

        $storagePath = "compliance_cases/{$caseId}/documents";
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs($storagePath, $filename);

        $document = $case->documents()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
        ]);

        assert($document instanceof ComplianceCaseDocument);

        return $document;
    }

    /**
     * Verify a document.
     */
    public function verifyDocument(int $documentId, int $verifiedBy): ComplianceCaseDocument
    {
        $document = ComplianceCaseDocument::findOrFail($documentId);
        $document->update([
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        return $document->fresh();
    }

    /**
     * Add a link to a case.
     */
    public function addLink(int $caseId, string $linkedType, int $linkedId): ComplianceCaseLink
    {
        $case = ComplianceCase::findOrFail($caseId);

        return $case->addLink($linkedType, $linkedId);
    }

    /**
     * Remove a link from a case.
     */
    public function removeLink(int $linkId): void
    {
        ComplianceCaseLink::findOrFail($linkId)->delete();
    }

    /**
     * Get all documents for a case.
     */
    public function getCaseDocuments(int $caseId): Collection
    {
        return ComplianceCase::findOrFail($caseId)->documents()->get();
    }

    /**
     * Get all links for a case.
     */
    public function getCaseLinks(int $caseId): Collection
    {
        return ComplianceCase::findOrFail($caseId)->links()->get();
    }

    /**
     * Proactively alert on open cases whose SLA deadline has passed.
     *
     * Designed to be called from the scheduler (see bootstrap notes: daily).
     * Raises ONE summary SystemAlert grouped by priority (no per-case spam)
     * and notifies each case's assignee individually so officers hear about
     * their own overdue cases.
     *
     * @return array{breached: int, notified: int, by_priority: array<string, int>}
     */
    public function alertBreachedCases(): array
    {
        $breached = ComplianceCase::with('assignee')
            ->open()
            ->where('sla_deadline', '<', now())
            ->orderBy('sla_deadline')
            ->get();

        if ($breached->isEmpty()) {
            return ['breached' => 0, 'notified' => 0, 'by_priority' => []];
        }

        $byPriority = [];
        foreach ($breached as $case) {
            $byPriority[$case->priority->value] = ($byPriority[$case->priority->value] ?? 0) + 1;
        }

        // Worst offenders: longest overdue first.
        $worstRefs = $breached
            ->take(5)
            ->map(fn (ComplianceCase $case) => sprintf(
                '%s (%s, %dh overdue)',
                $case->case_number,
                $case->priority->value,
                (int) $case->sla_deadline->diffInHours(now())
            ))
            ->implode(', ');

        $prioritySummary = implode(', ', array_map(
            fn ($priority, $count) => "{$count} {$priority}",
            array_keys($byPriority),
            $byPriority
        ));

        try {
            $this->alertService->critical(
                "SLA breached on {$breached->count()} open compliance case(s) [{$prioritySummary}]. Worst: {$worstRefs}",
                [
                    'source' => 'case_sla_breach',
                    'metadata' => [
                        'breached_count' => $breached->count(),
                        'by_priority' => $byPriority,
                        'worst_case_numbers' => $breached->take(5)->pluck('case_number')->all(),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to raise SLA breach summary alert: '.$e->getMessage());
        }

        $notified = 0;
        foreach ($breached as $case) {
            if (! $case->assignee) {
                continue;
            }

            try {
                $case->assignee->notify(new ComplianceCaseSlaBreachedNotification(
                    $case,
                    max(0, (int) $case->sla_deadline->diffInHours(now()))
                ));
                $notified++;
            } catch (\Throwable $e) {
                Log::error("Failed to notify assignee of SLA breach for case {$case->case_number}: ".$e->getMessage());
            }
        }

        return [
            'breached' => $breached->count(),
            'notified' => $notified,
            'by_priority' => $byPriority,
        ];
    }
}
