<?php

namespace App\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\CaseNoteType;
use App\Enums\CaseResolution;
use App\Enums\ComplianceCasePriority;
use App\Enums\ComplianceCaseStatus;
use App\Enums\ComplianceCaseType;
use App\Enums\FindingSeverity;
use App\Events\CaseOpened;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\Compliance\ComplianceFinding;
use App\Services\AlertTriageService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for managing compliance cases and their lifecycle.
 * Handles case creation, assignment, notes, and resolution.
 */
class CaseManagementService
{
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
     * Assign a case to an officer.
     */
    public function assignCase(ComplianceCase $case, int $officerId): ComplianceCase
    {
        $case->assignTo($officerId);

        return $case->fresh();
    }

    /**
     * Close a case.
     */
    public function closeCase(
        ComplianceCase $case,
        CaseResolution $resolution,
        ?string $notes = null
    ): ComplianceCase {
        $case->close($resolution, $notes);

        return $case->fresh();
    }

    /**
     * Escalate a case.
     */
    public function escalateCase(ComplianceCase $case): ComplianceCase
    {
        $case->escalate();

        return $case->fresh();
    }

    /**
     * Calculate SLA deadline based on severity and case type.
     */
    protected function calculateSlaDeadline(FindingSeverity $severity, ComplianceCaseType $caseType): Carbon
    {
        $hours = match ($severity) {
            FindingSeverity::Critical => 24,
            FindingSeverity::High => 48,
            FindingSeverity::Medium => 120,
            FindingSeverity::Low => 240,
        };

        if ($caseType === ComplianceCaseType::Str || $caseType === ComplianceCaseType::SanctionReview) {
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
     * Generate next case number.
     */
    public function generateCaseNumber(): string
    {
        $year = now()->year;
        $prefix = "CASE-{$year}-";

        // Use lock to prevent race conditions
        $lastCase = DB::table('compliance_cases')
            ->where('case_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderBy('case_number', 'desc')
            ->first();

        $newNumber = $lastCase ? ((int) substr($lastCase->case_number, -5)) + 1 : 1;

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a case from one or more alerts.
     */
    public function createFromAlerts(array $alertIds, int $openedBy): ComplianceCase
    {
        return DB::transaction(function () use ($alertIds, $openedBy) {
            $alerts = Alert::whereIn('id', $alertIds)->get();

            if ($alerts->isEmpty()) {
                throw new \InvalidArgumentException('No alerts provided');
            }

            $customerId = $alerts->first()->customer_id;
            $maxRiskScore = $alerts->max('risk_score');
            $priority = AlertPriority::fromRiskScore($maxRiskScore);

            $case = ComplianceCase::create([
                'case_number' => ComplianceCase::generateCaseNumber(),
                'customer_id' => $customerId,
                'status' => ComplianceCaseStatus::Open,
                'priority' => $priority,
                'assigned_to' => null,
                'opened_by' => $openedBy,
                'sla_deadline' => $this->calculateSlaDeadlineFromPriority($priority),
            ]);

            foreach ($alerts as $alert) {
                $alert->update(['case_id' => $case->id]);
            }

            event(new CaseOpened($case));

            return $case->load('alerts');
        });
    }

    /**
     * Link an alert to an existing case.
     */
    public function linkAlertToCase(Alert $alert, ComplianceCase $case): Alert
    {
        if ($alert->case_id && $alert->case_id !== $case->id) {
            throw new \InvalidArgumentException('Alert already linked to another case');
        }

        $alert->update(['case_id' => $case->id]);

        $this->recalculateCasePriority($case);
        $this->recalculateCaseSla($case);

        return $alert->fresh();
    }

    /**
     * Merge two cases together.
     */
    public function mergeCases(ComplianceCase $sourceCase, ComplianceCase $targetCase): ComplianceCase
    {
        return DB::transaction(function () use ($sourceCase, $targetCase) {
            Alert::where('case_id', $sourceCase->id)
                ->update(['case_id' => $targetCase->id]);

            $sourceCase->strDrafts()->update(['case_id' => $targetCase->id]);

            $sourceCase->update(['status' => ComplianceCaseStatus::Closed]);

            $this->recalculateCasePriority($targetCase);
            $this->recalculateCaseSla($targetCase);

            return $targetCase->fresh()->load('alerts');
        });
    }

    /**
     * Update case status.
     */
    public function updateStatus(ComplianceCase $case, ComplianceCaseStatus $status): ComplianceCase
    {
        $case->update(['status' => $status]);

        if ($status === ComplianceCaseStatus::Closed) {
            $case->update(['resolved_at' => now()]);
        }

        return $case->fresh();
    }

    /**
     * Assign case to an officer.
     */
    public function assignToOfficer(ComplianceCase $case, int $userId): ComplianceCase
    {
        $case->update(['assigned_to' => $userId]);

        if ($case->status === ComplianceCaseStatus::Open) {
            $case->update(['status' => ComplianceCaseStatus::UnderReview]);
        }

        return $case->fresh();
    }

    /**
     * Resolve a case (requires all alerts to be resolved).
     */
    public function resolveCase(ComplianceCase $case, int $resolvedBy, ?string $notes = null): ComplianceCase
    {
        if (! $case->canBeResolved()) {
            throw new \RuntimeException('Cannot resolve case: not all alerts are linked');
        }

        $case->update([
            'status' => ComplianceCaseStatus::Closed,
            'resolved_at' => now(),
        ]);

        return $case->fresh();
    }

    /**
     * Calculate SLA deadline based on priority.
     */
    protected function calculateSlaDeadlineFromPriority(AlertPriority $priority): Carbon
    {
        $hours = match ($priority) {
            AlertPriority::Critical => 4,
            AlertPriority::High => 8,
            AlertPriority::Medium => 24,
            AlertPriority::Low => 72,
        };

        return now()->addHours($hours);
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
    public function getOpenCases(): \Illuminate\Database\Eloquent\Collection
    {
        return ComplianceCase::with(['customer', 'assignedTo', 'alerts'])
            ->open()
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
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
                ->where('priority', AlertPriority::Critical)->count(),
            'high' => ComplianceCase::open()
                ->where('priority', AlertPriority::High)->count(),
            'medium' => ComplianceCase::open()
                ->where('priority', AlertPriority::Medium)->count(),
            'low' => ComplianceCase::open()
                ->where('priority', AlertPriority::Low)->count(),
            'overdue' => ComplianceCase::open()
                ->where('sla_deadline', '<', now())->count(),
            'pending_review' => ComplianceCase::where('status', ComplianceCaseStatus::PendingApproval)->count(),
        ];
    }

    /**
     * Find potential duplicate cases for a customer.
     */
    public function findPotentialDuplicates(int $customerId, ?int $excludeCaseId = null): \Illuminate\Database\Eloquent\Collection
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

        $storagePath = "compliance_cases/{$caseId}/documents";
        $filename = Str::uuid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs($storagePath, $filename);

        return $case->documents()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
        ]);
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
    public function getCaseDocuments(int $caseId): \Illuminate\Database\Eloquent\Collection
    {
        return ComplianceCase::findOrFail($caseId)->documents()->get();
    }

    /**
     * Get all links for a case.
     */
    public function getCaseLinks(int $caseId): \Illuminate\Database\Eloquent\Collection
    {
        return ComplianceCase::findOrFail($caseId)->links()->get();
    }
}
