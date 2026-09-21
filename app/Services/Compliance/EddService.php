<?php

namespace App\Services\Compliance;

use App\Enums\CddLevel;
use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Exceptions\Domain\EddValidationException;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\User;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;

class EddService
{
    protected MathService $mathService;

    protected ComplianceService $complianceService;

    public function __construct(MathService $mathService, ComplianceService $complianceService)
    {
        $this->mathService = $mathService;
        $this->complianceService = $complianceService;
    }

    public function createEddRecord(FlaggedTransaction $flag, array $data = []): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($flag, $data) {
            $eddReference = $this->generateEddReference();

            $riskLevel = $data['risk_level'] ?? EddRiskLevel::Medium;
            $riskLevelEnum = $riskLevel instanceof EddRiskLevel
                ? $riskLevel
                : EddRiskLevel::tryFrom(strtolower((string) $riskLevel));
            if (! $riskLevelEnum) {
                throw new \InvalidArgumentException("Invalid EDD risk level: {$riskLevel}");
            }

            $recordData = [
                'customer_id' => $flag->customer_id ?? $flag->getAttribute('customer_id'),
                'edd_reference' => $eddReference,
                'status' => EddStatus::Incomplete,
                'risk_level' => $riskLevelEnum->value,
            ];

            // Only set flagged_transaction_id if the flag has an ID (is saved)
            if ($flag->id) {
                $recordData['flagged_transaction_id'] = $flag->id;
            }

            $record = EnhancedDiligenceRecord::create($recordData);

            return $record;
        });
    }

    public function updateEddRecord(EnhancedDiligenceRecord $record, array $data): EnhancedDiligenceRecord
    {
        $record->update($data);

        if ($this->isRecordComplete($record)) {
            $record->update(['status' => EddStatus::PendingReview]);
        }

        return $record->fresh();
    }

    public function submitForReview(EnhancedDiligenceRecord $record): EnhancedDiligenceRecord
    {
        if (! $this->isRecordComplete($record)) {
            throw new EddValidationException('EDD record must be complete before submission');
        }

        $record->update(['status' => EddStatus::PendingReview]);

        return $record;
    }

    /**
     * Record the questionnaire responses and move the record to
     * QuestionnaireSubmitted. The status gate is re-checked under a row
     * lock so a concurrent submit/review cannot double-write.
     *
     * @param  array<string, mixed>  $responses
     */
    public function submitQuestionnaire(EnhancedDiligenceRecord $record, array $responses, int $userId): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($record, $responses, $userId) {
            $locked = EnhancedDiligenceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canSubmitQuestionnaire()) {
                throw new EddValidationException('Cannot submit questionnaire in current status.');
            }

            $locked->update([
                'questionnaire_responses' => $responses,
                'questionnaire_completed_at' => now(),
                'questionnaire_completed_by' => $userId,
                'status' => EddStatus::QuestionnaireSubmitted,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Statuses a record must be in before it can be finalised (approved or
     * rejected). Shared by the web and API review surfaces.
     *
     * @return array<int, EddStatus>
     */
    public function finalisableStatuses(): array
    {
        return [EddStatus::QuestionnaireSubmitted, EddStatus::PendingReview];
    }

    /**
     * Approve an EDD record. The finalisable-status gate is re-checked under
     * a row lock so two reviewers cannot both finalise the same record.
     */
    public function approve(EnhancedDiligenceRecord $record, User $reviewer, ?string $notes = null): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($record, $reviewer, $notes) {
            $locked = EnhancedDiligenceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertFinalisable($locked, 'approved');

            $locked->update([
                'status' => EddStatus::Approved,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'review_notes' => $notes ?? $locked->review_notes,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Reject an EDD record. Same locking as approve().
     */
    public function reject(EnhancedDiligenceRecord $record, User $reviewer, string $reason): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($record, $reviewer, $reason) {
            $locked = EnhancedDiligenceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertFinalisable($locked, 'rejected');

            $locked->update([
                'status' => EddStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @throws EddValidationException when the record is not finalisable
     */
    private function assertFinalisable(EnhancedDiligenceRecord $record, string $action): void
    {
        if (! in_array($record->status, $this->finalisableStatuses(), true)) {
            throw new EddValidationException(
                "Only records with a submitted questionnaire or in Pending Review can be {$action} (current: {$record->status->value})."
            );
        }
    }

    public function isRecordComplete(EnhancedDiligenceRecord $record): bool
    {
        $source = is_string($record->source_of_funds) ? trim($record->source_of_funds) : null;
        $purpose = is_string($record->purpose_of_transaction) ? trim($record->purpose_of_transaction) : null;

        if ($source === null || $source === '' || $purpose === null || $purpose === '') {
            return false;
        }

        // For Enhanced CDD (High risk), also verify all required documents are uploaded
        if ($record->risk_level === EddRiskLevel::High) {
            $customer = $record->customer;
            if ($customer instanceof Customer) {
                $documentCheck = $this->complianceService->verifyCddDocuments($customer, CddLevel::Enhanced);
                if (! $documentCheck['is_compliant']) {
                    return false;
                }
            }
        }

        return true;
    }

    public function expireRecords(?int $maxAgeDays = 365): int
    {
        $expiredAt = now()->subDays($maxAgeDays);

        // Single UPDATE — the per-row loop issued N statements for what the
        // query planner can do in one.
        return EnhancedDiligenceRecord::where('status', '!=', EddStatus::Expired->value)
            ->where('updated_at', '<=', $expiredAt)
            ->update(['status' => EddStatus::Expired->value]);
    }

    protected function generateEddReference(): string
    {
        $prefix = 'EDD-'.date('Ym').'-';
        $lastRecord = EnhancedDiligenceRecord::where('edd_reference', 'like', $prefix.'%')
            ->orderBy('edd_reference', 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->edd_reference, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }
}
