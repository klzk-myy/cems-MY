<?php

namespace App\Services;

use App\Models\CustomerDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer Document Service
 *
 * Handles all customer document-related business logic including:
 * - Document status checks
 * - Document expiry management
 * - Document verification workflow
 *
 * This service removes business logic from the CustomerDocument model,
 * ensuring proper MVC separation of concerns.
 */
class CustomerDocumentService
{
    /**
     * Check if a document is verified.
     *
     * @param  CustomerDocument  $document  Document to check
     * @return bool True if document is verified
     */
    public function isVerified(CustomerDocument $document): bool
    {
        return $document->verified_by !== null && $document->verified_at !== null;
    }

    /**
     * Check if a document is expired.
     *
     * @param  CustomerDocument  $document  Document to check
     * @return bool True if document is expired
     */
    public function isExpired(CustomerDocument $document): bool
    {
        return $document->expiry_date !== null && $document->expiry_date->isPast();
    }

    /**
     * Check if a document is expiring soon (within 30 days).
     *
     * @param  CustomerDocument  $document  Document to check
     * @return bool True if document is expiring soon
     */
    public function isExpiringSoon(CustomerDocument $document): bool
    {
        if ($document->expiry_date === null) {
            return false;
        }

        return $document->expiry_date->isFuture() && $document->expiry_date->diffInDays(now()) <= 30;
    }

    /**
     * Get verified documents for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @return Collection Collection of verified documents
     */
    public function getVerifiedDocuments(int $customerId): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->whereNotNull('verified_by')
            ->whereNotNull('verified_at')
            ->get();
    }

    /**
     * Get unverified documents for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @return Collection Collection of unverified documents
     */
    public function getUnverifiedDocuments(int $customerId): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->whereNull('verified_by')
            ->whereNull('verified_at')
            ->get();
    }

    /**
     * Get expired documents for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @return Collection Collection of expired documents
     */
    public function getExpiredDocuments(int $customerId): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->get();
    }

    /**
     * Get documents expiring soon for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @param  int  $days  Days threshold (default: 30)
     * @return Collection Collection of documents expiring soon
     */
    public function getDocumentsExpiringSoon(int $customerId, int $days = 30): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($days))
            ->get();
    }

    /**
     * Get all documents for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @return Collection Collection of documents
     */
    public function getDocumentsForCustomer(int $customerId): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get documents by type for a customer.
     *
     * @param  int  $customerId  Customer ID
     * @param  string  $documentType  Document type
     * @return Collection Collection of documents
     */
    public function getDocumentsByType(int $customerId, string $documentType): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->where('document_type', $documentType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get documents requiring attention (expired or expiring soon).
     *
     * @param  int  $customerId  Customer ID
     * @return Collection Collection of documents requiring attention
     */
    public function getDocumentsRequiringAttention(int $customerId): Collection
    {
        return CustomerDocument::where('customer_id', $customerId)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('expiry_date')
                        ->where('expiry_date', '<', now());
                })->orWhere(function ($q) {
                    $q->whereNotNull('expiry_date')
                        ->where('expiry_date', '>', now())
                        ->where('expiry_date', '<=', now()->addDays(30));
                });
            })
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Verify a document.
     *
     * @param  CustomerDocument  $document  Document to verify
     * @param  int  $userId  User ID verifying the document
     * @param  string|null  $notes  Verification notes
     * @return CustomerDocument Updated document
     */
    public function verifyDocument(CustomerDocument $document, int $userId, ?string $notes = null): CustomerDocument
    {
        $document->update([
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);

        return $document->fresh();
    }

    /**
     * Get days until expiry.
     *
     * @param  CustomerDocument  $document  Document to check
     * @return int|null Days until expiry, or null if no expiry date
     */
    public function getDaysUntilExpiry(CustomerDocument $document): ?int
    {
        if ($document->expiry_date === null) {
            return null;
        }

        if ($document->expiry_date->isPast()) {
            return 0;
        }

        return $document->expiry_date->diffInDays(now());
    }
}
