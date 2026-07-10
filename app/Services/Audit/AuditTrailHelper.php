<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\AuditService;

/**
 * Helper that records auditable events to both the application audit_trails
 * table and the tamper-evident system_logs stream via AuditService.
 *
 * The dual-write design preserves the existing system_logs chain (hashed,
 * sequential, tamper-evident) while also populating the richer audit_trails
 * table used for business-level querying and reporting.
 */
class AuditTrailHelper
{
    public function __construct(protected AuditService $auditService) {}

    public function record(
        string $auditableType,
        int $auditableId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        ?string $ipAddress = null
    ): AuditTrail {
        return AuditTrail::create([
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action' => $action,
            'user_id' => $user?->id,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }

    public function recordTransaction(
        int $transactionId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        $auditTrail = $this->record('Transaction', $transactionId, $action, $metadata, $user, $ipAddress);

        try {
            $this->auditService->logTransaction($action, $transactionId, [
                'old' => $metadata['old'] ?? [],
                'new' => $metadata['new'] ?? [],
                'severity' => $severity,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            \Log::error('AuditService transaction write failed', [
                'action' => $action,
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $auditTrail;
    }

    public function recordCustomer(
        int $customerId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        $auditTrail = $this->record('Customer', $customerId, $action, $metadata, $user, $ipAddress);

        try {
            $this->auditService->logCustomer($action, $customerId, [
                'old' => $metadata['old'] ?? [],
                'new' => $metadata['new'] ?? [],
                'severity' => $severity,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            \Log::error('AuditService customer write failed', [
                'action' => $action,
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $auditTrail;
    }
}
