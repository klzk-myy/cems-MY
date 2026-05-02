<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Enums\EddStatus;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\User;
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

            $recordData = [
                'customer_id' => $flag->customer_id ?? $flag->getAttribute('customer_id'),
                'edd_reference' => $eddReference,
                'status' => EddStatus::Incomplete,
                'risk_level' => $data['risk_level'] ?? 'Medium',
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
            throw new \InvalidArgumentException('EDD record must be complete before submission');
        }

        $record->update(['status' => EddStatus::PendingReview]);

        return $record;
    }

    public function approve(EnhancedDiligenceRecord $record, User $reviewer, ?string $notes = null): EnhancedDiligenceRecord
    {
        $record->update([
            'status' => EddStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $record;
    }

    public function reject(EnhancedDiligenceRecord $record, User $reviewer, string $reason): EnhancedDiligenceRecord
    {
        $record->update([
            'status' => EddStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        return $record;
    }

    public function isRecordComplete(EnhancedDiligenceRecord $record): bool
    {
        $source = is_string($record->source_of_funds) ? trim($record->source_of_funds) : null;
        $purpose = is_string($record->purpose_of_transaction) ? trim($record->purpose_of_transaction) : null;

        if ($source === null || $source === '' || $purpose === null || $purpose === '') {
            return false;
        }

        // For Enhanced CDD (High risk), also verify all required documents are uploaded
        if ($record->risk_level === 'High') {
            $customer = $record->customer;
            if ($customer) {
                $documentCheck = $this->complianceService->verifyCddDocuments($customer, CddLevel::Enhanced);
                if (! $documentCheck['is_compliant']) {
                    return false;
                }
            }
        }

        return true;
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
