<?php

namespace App\Services\Compliance;

use App\Enums\CddLevel;
use App\Enums\CustomerDocumentStatus;
use App\Enums\DocumentType;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Services\AuditService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class KycDocumentExpiryService
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected AuditService $auditService,
    ) {}

    /**
     * Determine whether ALL verified identity documents are expired
     * (past grace period). Customers without any documents are NOT
     * blocked by this check — document-less customers keep transacting
     * as before; only customers with documents whose identity documents
     * have all lapsed are blocked.
     */
    /**
     * The booking gate (TransactionCreationService). Scope: only customers
     * whose identity documents have ALL lapsed past the grace period.
     * Document-less customers deliberately keep transacting — absence of
     * documents is a CDD data-collection gap, not a block.
     *
     * Must NOT be confused with mustBlockDueToExpiredDocuments(), which is a
     * stricter superset (no docs / missing CDD-level docs / any expired doc)
     * used for enforcement surfaces outside booking.
     */
    public function hasAllIdentityDocumentsExpired(Customer $customer): bool
    {
        // Preserve legacy behaviour: no documents at all -> do not block.
        if (! $customer->documents()->exists()) {
            return false;
        }

        $cutoffDate = $this->graceCutoffDate();

        // A single exists() query: any verified identity document that is
        // un-expired (or has no expiry) means the customer may transact.
        return ! $customer->documents()
            ->verified()
            ->whereIn('document_type', [DocumentType::MyKad->value, DocumentType::Passport->value])
            ->where(function (Builder $query) use ($cutoffDate) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $cutoffDate);
            })
            ->exists();
    }

    /**
     * Sweep: mark verified documents past their expiry date (including
     * grace period) as expired. Returns the number of documents updated.
     */
    public function expireDocuments(): int
    {
        return CustomerDocument::query()
            ->verified()
            ->where('status', '!=', CustomerDocumentStatus::Expired->value)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $this->graceCutoffDate())
            ->update(['status' => CustomerDocumentStatus::Expired->value]);
    }

    protected function graceCutoffDate(): Carbon
    {
        return Carbon::now()->subDays($this->thresholdService->getKycGracePeriodDays());
    }

    /**
     * Stricter enforcement superset: blocks when the customer has no
     * documents at all, is missing CDD-level required documents, or has any
     * verified document expired past the grace period. Not used by the
     * booking gate — see hasAllIdentityDocumentsExpired() for the booking
     * scope and why the two differ.
     */
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
        $cutoffDate = $this->graceCutoffDate();

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
        $cddLevel = $customer->cdd_level ?? CddLevel::Standard;

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
