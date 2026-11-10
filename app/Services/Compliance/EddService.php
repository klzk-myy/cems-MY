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
use Illuminate\Database\UniqueConstraintViolationException;
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
        // Bounded retry: two concurrent EDD creations can read the same last
        // reference and compute the same next number — the unique index on
        // edd_reference rejects the loser, so regenerate the reference and
        // retry the whole transaction rather than surfacing a 500.
        $maxRetries = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
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

                    return EnhancedDiligenceRecord::create($recordData);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $maxRetries || ! str_contains($e->getMessage(), 'edd_reference')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Apply field updates and auto-promote to PendingReview when the record
     * becomes complete. The completeness probe and status transition run on
     * the locked row so a concurrent approve/reject cannot be rewound to
     * PendingReview by a late update.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEddRecord(EnhancedDiligenceRecord $record, array $data): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $locked = EnhancedDiligenceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data);

            if ($this->isRecordComplete($locked) && ! $this->isTerminalStatus($locked->status)) {
                $locked->status = EddStatus::PendingReview;
            }

            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Submit a complete record for review. Same locking as approve(): the
     * completeness gate and the status write both act on the locked row,
     * and a record already in a terminal status can no longer be rewound.
     */
    public function submitForReview(EnhancedDiligenceRecord $record): EnhancedDiligenceRecord
    {
        return DB::transaction(function () use ($record) {
            $locked = EnhancedDiligenceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();

            if (! $this->isRecordComplete($locked)) {
                throw new EddValidationException('EDD record must be complete before submission');
            }

            if ($this->isTerminalStatus($locked->status)) {
                throw new EddValidationException('Cannot submit a finalised EDD record for review.');
            }

            $locked->status = EddStatus::PendingReview;
            $locked->save();

            return $locked;
        });
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

            // Direct assignment + save(): status is intentionally not in
            // $fillable, so ->update() would silently drop it.
            $locked->questionnaire_responses = $responses;
            $locked->questionnaire_completed_at = now();
            $locked->questionnaire_completed_by = $userId;
            $locked->status = EddStatus::QuestionnaireSubmitted;
            $locked->save();

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

            $locked->status = EddStatus::Approved;
            $locked->approved_by = $reviewer->id;
            $locked->approved_at = now();
            $locked->review_notes = $notes ?? $locked->review_notes;
            $locked->save();

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

            $locked->status = EddStatus::Rejected;
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->review_notes = $reason;
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Statuses that must never be overwritten by a status transition —
     * Approved/Rejected are review outcomes and Expired is end-of-life.
     */
    private function isTerminalStatus(EddStatus $status): bool
    {
        return in_array($status, [EddStatus::Approved, EddStatus::Rejected, EddStatus::Expired], true);
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
        // lockForUpdate inside createEddRecord's transaction gap-locks the
        // prefix range so a concurrent insert cannot reuse the same number —
        // same idiom as StockTransfer::generateTransferNumber().
        $lastRecord = EnhancedDiligenceRecord::where('edd_reference', 'like', $prefix.'%')
            ->orderBy('edd_reference', 'desc')
            ->lockForUpdate()
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
