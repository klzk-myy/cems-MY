<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Support\Collection;

class KycDocumentExpiryService
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected AuditService $auditService
    ) {}

    public function mustBlockDueToExpiredDocuments(Customer $customer): bool
    {
        $requiredDocs = $this->getRequiredDocumentTypes($customer->cdd_level);

        foreach ($requiredDocs as $docType) {
            $doc = $customer->documents()->where('document_type', $docType)->first();
            if (! $doc || $this->isExpiredWithGrace($doc)) {
                return true;
            }
        }

        return false;
    }

    public function getExpiredDocuments(Customer $customer): Collection
    {
        $requiredDocs = $this->getRequiredDocumentTypes($customer->cdd_level);

        return $customer->documents()
            ->whereIn('document_type', $requiredDocs)
            ->get()
            ->filter(fn ($doc) => $this->isExpiredWithGrace($doc));
    }

    protected function getRequiredDocumentTypes(?CddLevel $cddLevel): array
    {
        return match ($cddLevel) {
            CddLevel::Enhanced => ['MyKad', 'Proof_of_Address', 'Passport'],
            CddLevel::Standard => ['MyKad', 'Proof_of_Address'],
            default => ['MyKad'],
        };
    }

    protected function isExpiredWithGrace(CustomerDocument $doc): bool
    {
        if ($doc->expiry_date === null) {
            return false;
        }

        $graceDays = $this->thresholdService->getKycGracePeriodDays();
        $graceEndDate = $doc->expiry_date->copy()->addDays($graceDays);

        return now()->isAfter($graceEndDate);
    }
}
