<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\PepType;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\User;

/**
 * PEP Approval Service
 *
 * Handles head office Senior Management approval for PEP customers per pd-00.md 14C.13.1(d):
 * "obtaining approval from the Senior Management of the reporting institution before establishing
 * (or continuing, for existing customer) such business relationship with the customer.
 * In the case of PEPs, Senior Management refers to Senior Management at the head office."
 *
 * Approval is required for:
 * - Foreign PEPs (always require head office approval)
 * - Domestic PEPs assessed as higher risk (Medium/High risk rating)
 */
class PepApprovalService
{
    /**
     * Check if a customer requires head office Senior Management approval for PEP relationship.
     *
     * @param  Customer  $customer  The customer to check
     * @return bool True if head office approval is required
     */
    public function requiresHeadOfficeApproval(Customer $customer): bool
    {
        // Non-PEP customers don't need approval
        if (! $customer->is_pep) {
            return false;
        }

        // Foreign PEPs always require head office approval (per pd-00.md 15.2)
        $pepType = $this->getPepType($customer);
        if ($pepType === PepType::Foreign) {
            return true;
        }

        // Domestic PEPs require head office approval only if higher risk
        if ($pepType === PepType::Domestic) {
            return $customer->isHigherRisk();
        }

        // International Organisation, Family Member, Close Associate - check risk
        // These categories use risk-based approach
        return $customer->isHigherRisk();
    }

    /**
     * Get the PEP type for a customer.
     */
    protected function getPepType(Customer $customer): ?PepType
    {
        // Check if customer has pep_type attribute set
        if (isset($customer->pep_type) && $customer->pep_type) {
            return PepType::tryFrom($customer->pep_type);
        }

        // Fallback: infer from PEP status and related fields
        if (! $customer->is_pep) {
            return null;
        }

        // If customer has former_pep_domain set, they may be a former PEP
        // but we still treat them as needing approval if marked as PEP
        return PepType::Domestic; // Default to Domestic if pep_type not set
    }

    /**
     * Request head office approval for a PEP customer.
     *
     * @param  Customer  $customer  The PEP customer
     * @param  string  $transactionType  Type of transaction (e.g., 'new_relationship', 'continued_relationship')
     * @return PepApprovalRequest The created approval request
     */
    public function requestApproval(Customer $customer, string $transactionType): PepApprovalRequest
    {
        return PepApprovalRequest::create([
            'customer_id' => $customer->id,
            'transaction_type' => $transactionType,
            'status' => ApprovalStatus::Pending,
            'approval_level' => 'head_office_senior_management',
            'requested_at' => now(),
        ]);
    }

    /**
     * Approve a PEP approval request.
     *
     * @param  PepApprovalRequest  $request  The approval request
     * @param  User  $approver  The user approving the request
     */
    public function approve(PepApprovalRequest $request, User $approver): void
    {
        $request->update([
            'status' => ApprovalStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject a PEP approval request.
     *
     * @param  PepApprovalRequest  $request  The approval request
     * @param  User  $rejector  The user rejecting the request
     * @param  string  $reason  The rejection reason
     */
    public function reject(PepApprovalRequest $request, User $rejector, string $reason): void
    {
        $request->update([
            'status' => ApprovalStatus::Rejected,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Check if a customer has an active (pending) approval request.
     */
    public function hasPendingApproval(Customer $customer): bool
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Pending)
            ->exists();
    }

    /**
     * Get the most recent pending approval request for a customer.
     */
    public function getPendingApproval(Customer $customer): ?PepApprovalRequest
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Pending)
            ->latest()
            ->first();
    }

    /**
     * Check if a customer has an approved PEP approval.
     */
    public function hasApprovedApproval(Customer $customer): bool
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Approved)
            ->exists();
    }
}
