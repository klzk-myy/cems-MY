<?php

namespace App\Services\Compliance;

use App\Enums\ApprovalLevel;
use App\Enums\ApprovalStatus;
use App\Enums\PepType;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        protected AuditService $auditService
    ) {}

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
     *
     * Fail-closed: a missing or unrecognised pep_type is treated as Foreign,
     * because per pd-00.md 15.2 foreign PEPs ALWAYS require head-office
     * Senior Management approval. Defaulting to Domestic (risk-based) would
     * let unknown-PEP-type customers transact without that approval.
     */
    protected function getPepType(Customer $customer): ?PepType
    {
        // Check if customer has pep_type attribute set
        if (isset($customer->pep_type) && $customer->pep_type) {
            return PepType::tryFrom($customer->pep_type) ?? PepType::Foreign;
        }

        // Fallback: infer from PEP status and related fields
        if (! $customer->is_pep) {
            return null;
        }

        // PEP flagged but pep_type not set - assume the strictest category.
        return PepType::Foreign;
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
        if (! $customer->is_pep) {
            throw new PepApprovalRequiredException('Customer is not flagged as PEP');
        }

        // Lock the customer row as the mutex: without it two concurrent
        // requests for the same transaction type can both pass the
        // pending-check and create duplicate approval requests.
        return DB::transaction(function () use ($customer, $transactionType) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if (PepApprovalRequest::where('customer_id', $customer->id)
                ->where('transaction_type', $transactionType)
                ->where('status', ApprovalStatus::Pending->value)
                ->exists()) {
                throw new PepApprovalRequiredException('Pending approval already exists for this customer and transaction type');
            }

            return PepApprovalRequest::create([
                'customer_id' => $customer->id,
                'transaction_type' => $transactionType,
                'status' => ApprovalStatus::Pending,
                'approval_level' => 'head_office_senior_management',
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * Approve a PEP approval request.
     *
     * @param  PepApprovalRequest  $request  The approval request
     * @param  User  $approver  The user approving the request
     */
    public function approve(PepApprovalRequest $request, User $approver): void
    {
        // Lock + re-check inside the transaction: two concurrent approvers
        // could otherwise both pass the Pending check and double-approve.
        DB::transaction(function () use ($request, $approver) {
            $locked = PepApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ApprovalStatus::Pending) {
                throw new PepApprovalRequiredException('Cannot approve: request is not in Pending status');
            }

            if ($this->isSelfApproval($locked, $approver)) {
                throw new PepApprovalRequiredException(
                    'Self-approval forbidden: segregation of duties requires an approver who did not initiate the PEP relationship'
                );
            }

            $previousStatus = $locked->status;

            $locked->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->auditService->logComplianceDecision('pep_approval_approved', $locked->id, [
                'entity_type' => 'PepApprovalRequest',
                'old' => ['status' => $previousStatus->value],
                'new' => [
                    'status' => ApprovalStatus::Approved->value,
                    'approved_by' => $approver->id,
                    'customer_id' => $locked->customer_id,
                    'transaction_type' => $locked->transaction_type,
                    'approval_level' => $locked->approval_level instanceof ApprovalLevel
                        ? $locked->approval_level->value
                        : $locked->approval_level,
                ],
            ]);
        });
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
        DB::transaction(function () use ($request, $rejector, $reason) {
            $locked = PepApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ApprovalStatus::Pending) {
                throw new PepApprovalRequiredException('Cannot reject: request is not in Pending status');
            }

            if ($this->isSelfApproval($locked, $rejector)) {
                throw new PepApprovalRequiredException(
                    'Self-approval forbidden: segregation of duties requires a decision-maker who did not initiate the PEP relationship'
                );
            }

            $previousStatus = $locked->status;

            $locked->update([
                'status' => ApprovalStatus::Rejected,
                'rejected_by' => $rejector->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditService->logComplianceDecision('pep_approval_rejected', $locked->id, [
                'entity_type' => 'PepApprovalRequest',
                'old' => ['status' => $previousStatus->value],
                'new' => [
                    'status' => ApprovalStatus::Rejected->value,
                    'rejected_by' => $rejector->id,
                    'rejection_reason' => $reason,
                    'customer_id' => $locked->customer_id,
                    'transaction_type' => $locked->transaction_type,
                ],
            ]);
        });
    }

    /**
     * Determine whether the proposed decision-maker is effectively the requester.
     *
     * pep_approval_requests does not persist the requesting user (the staff
     * member whose transaction attempt triggered the requirement), so the
     * closest available proxy is used: if the prospective approver is themself
     * the teller who created transactions for this customer, approving their
     * own PEP relationship would breach segregation of duties.
     */
    public function isSelfApproval(PepApprovalRequest $request, User $decisionMaker): bool
    {
        return Transaction::where('customer_id', $request->customer_id)
            ->where('user_id', $decisionMaker->id)
            ->exists();
    }

    /**
     * Check if a customer has an active (pending) approval request.
     */
    public function hasPendingApproval(Customer $customer): bool
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->exists();
    }

    /**
     * Get the most recent pending approval request for a customer.
     */
    public function getPendingApproval(Customer $customer): ?PepApprovalRequest
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->latest()
            ->first();
    }

    /**
     * Check if a customer has an approved PEP approval.
     */
    public function hasApprovedApproval(Customer $customer): bool
    {
        return PepApprovalRequest::where('customer_id', $customer->id)
            ->where('status', ApprovalStatus::Approved->value)
            ->exists();
    }
}
