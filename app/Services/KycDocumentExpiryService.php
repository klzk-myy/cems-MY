<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class KycDocumentExpiryService
{
    public function mustBlockDueToExpiredDocuments(Customer $customer): bool
    {
        // Block if customer has no documents at all
        if ($this->customerHasNoDocuments($customer)) {
            return true;
        }

        // Block if customer is missing CDD-level required documents
        if ($this->isMissingRequiredDocuments($customer)) {
            return true;
        }

        // Block if any verified document is expired (past grace period)
        return $this->getExpiredDocuments($customer)->isNotEmpty();
    }

    public function getExpiredDocuments(Customer $customer): Collection
    {
        $gracePeriodDays = config('thresholds.kyc.grace_period_days', 5);
        $cutoffDate = Carbon::now()->subDays($gracePeriodDays);

        return $customer->documents()
            ->verified()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $cutoffDate)
            ->get();
    }

    protected function customerHasNoDocuments(Customer $customer): bool
    {
        return $customer->documents()->count() === 0;
    }

    protected function isMissingRequiredDocuments(Customer $customer): bool
    {
        $cddLevel = $customer->cdd_level;

        // All CDD levels require MyKad (or Passport for foreigners)
        $hasIdentityDocument = $customer->documents()
            ->verified()
            ->whereIn('document_type', ['MyKad', 'Passport'])
            ->exists();

        if (! $hasIdentityDocument) {
            return true;
        }

        // Standard and Enhanced CDD require Proof of Address
        if (in_array($cddLevel, [CddLevel::Standard, CddLevel::Enhanced])) {
            $hasPoa = $customer->documents()
                ->verified()
                ->where('document_type', 'Proof_of_Address')
                ->exists();

            if (! $hasPoa) {
                return true;
            }
        }

        // Enhanced CDD also requires Passport
        if ($cddLevel === CddLevel::Enhanced) {
            $hasPassport = $customer->documents()
                ->verified()
                ->where('document_type', 'Passport')
                ->exists();

            if (! $hasPassport) {
                return true;
            }
        }

        return false;
    }
}
