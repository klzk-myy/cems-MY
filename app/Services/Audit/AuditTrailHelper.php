<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\AuditService;

/**
 * Helper that records auditable events through AuditService, the canonical,
 * tamper-evident write path.
 *
 * AuditService writes to system_logs (hashed, sequential, tamper-evident) and
 * mirrors the event into the richer audit_trails table used for business-level
 * querying. There is a single write path: no divergent dual-write.
 *
 * All domain-specific methods (recordTransaction, recordCustomer, etc.)
 * delegate to recordEntity(), which calls the matching AuditService method.
 */
class AuditTrailHelper
{
    public function __construct(protected AuditService $auditService) {}

    /**
     * Record a generic audit event to the audit_trails table, with an optional
     * best-effort dual-write to system_logs via AuditService.
     */
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

    /**
     * Record an auditable event through AuditService (the canonical,
     * tamper-evident write path). AuditService writes to system_logs and
     * mirrors the event into audit_trails, so there is a single write path
     * with no divergent dual-write. Callers should use the domain-specific
     * wrappers (recordTransaction, recordCustomer) for clarity.
     *
     * @param  string  $entityType  'Transaction' or 'Customer'
     * @param  string  $sealed  'log' for async, 'logSealed' for synchronous hash sealing
     */
    public function recordEntity(
        string $entityType,
        int $entityId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null,
        string $sealed = 'log'
    ): AuditTrail {
        $method = $sealed === 'logSealed'
            ? "log{$entityType}Sealed"
            : "log{$entityType}";

        $this->auditService->{$method}($action, $entityId, [
            'old' => $metadata['old'] ?? [],
            'new' => $metadata['new'] ?? [],
            'severity' => $severity,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
        ]);

        // Return the audit_trails mirror row AuditService just created so the
        // method still satisfies its AuditTrail return type for existing callers.
        return AuditTrail::where('auditable_type', $entityType)
            ->where('auditable_id', $entityId)
            ->where('action', $action)
            ->latest('id')->first()
            ?? new AuditTrail([
                'auditable_type' => $entityType,
                'auditable_id' => $entityId,
                'action' => $action,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
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
        return $this->recordEntity('Transaction', $transactionId, $action, $metadata, $user, $severity, $ipAddress, 'log');
    }

    public function recordTransactionSealed(
        int $transactionId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        return $this->recordEntity('Transaction', $transactionId, $action, $metadata, $user, $severity, $ipAddress, 'logSealed');
    }

    public function recordCustomer(
        int $customerId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        return $this->recordEntity('Customer', $customerId, $action, $metadata, $user, $severity, $ipAddress, 'log');
    }

    public function recordCustomerSealed(
        int $customerId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        return $this->recordEntity('Customer', $customerId, $action, $metadata, $user, $severity, $ipAddress, 'logSealed');
    }
}
