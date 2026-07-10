<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\AuditService;

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

        $this->auditService->logTransaction($action, $transactionId, [
            'old' => $metadata['old'] ?? [],
            'new' => $metadata['new'] ?? [],
            'severity' => $severity,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
        ]);

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

        $this->auditService->logCustomer($action, $customerId, [
            'old' => $metadata['old'] ?? [],
            'new' => $metadata['new'] ?? [],
            'severity' => $severity,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
        ]);

        return $auditTrail;
    }
}
