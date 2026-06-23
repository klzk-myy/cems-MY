<?php

namespace App\Services\Contracts;

use App\Models\SystemLog;

interface AuditServiceInterface
{
    public function computeEntryHash(
        string $timestamp,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $previousHash
    ): string;

    public function logWithSeverity(
        string $action,
        array $data = [],
        string $severity = 'INFO'
    ): SystemLog;

    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $oldValues = [],
        array $newValues = []
    ): SystemLog;

    public function logTransaction(
        string $action,
        int $transactionId,
        array $data = []
    ): SystemLog;

    public function logCustomer(
        string $action,
        int $customerId,
        array $data = []
    ): SystemLog;

    public function logComplianceDecision(string $action, int $entityId, array $data = [], string $severity = 'INFO'): SystemLog;

    public function logCddDecision(int $transactionId, string $cddLevel, array $triggers = []): SystemLog;

    public function logMfaEvent(string $action, ?int $userId = null, array $data = []): SystemLog;

    public function logStockTransferEvent(string $action, int $transferId, array $data = []): SystemLog;

    public function logJournalWorkflowEvent(string $action, int $entryId, array $data = []): SystemLog;

    public function logComplianceAlertEvent(string $action, int $alertId, array $data = []): SystemLog;

    public function logComplianceCaseEvent(string $action, int $caseId, array $data = []): SystemLog;

    public function logEddTemplateEvent(string $action, int $templateId, array $data = []): SystemLog;

    public function logRegulatoryReportEvent(string $action, int $reportId, array $data = []): SystemLog;

    public function logSessionEvent(string $action, array $data = []): SystemLog;

    public function logPermissionDenied(string $resource, string $action, string $reason, array $data = []): SystemLog;

    public function logCustomerRiskEvent(string $action, int $customerId, array $data = []): SystemLog;

    public function logAmlMonitorEvent(string $action, ?int $entityId = null, array $data = []): SystemLog;

    public function logSanctionEvent(string $action, ?int $entityId = null, array $data = []): SystemLog;

    public function logPositionEvent(string $action, array $data = []): SystemLog;

    public function logReportAccessEvent(string $action, array $data = []): SystemLog;

    public function logApiAccessEvent(string $action, array $data = []): SystemLog;

    public function logBranchAccessEvent(int $accessedBranchId, string $resource, int $resourceId, array $data = []): SystemLog;

    public function logBatchOperationEvent(string $action, array $data = []): SystemLog;

    public function verifyChainIntegrity(?int $limit = null): array;

    public function getUnsealedCount(): int;

    public function logProcedureTrigger(string $procedureName, array $parameters = []): SystemLog;

    public function logControllerAction(
        string $controller,
        string $action,
        ?int $userId,
        array $requestData = [],
        array $result = []
    ): SystemLog;

    public function logModelEvent(
        string $model,
        string $event,
        ?int $modelId,
        array $changes = [],
        array $original = []
    ): SystemLog;

    public function logTransactionWorkflow(
        string $step,
        int $transactionId,
        string $status,
        array $context = []
    ): SystemLog;

    public function logBatch(array $logs): bool;
}
