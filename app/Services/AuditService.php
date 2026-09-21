<?php

namespace App\Services;

use App\Enums\SystemLogSeverity;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\AuditTrail;
use App\Models\SystemLog;
use App\Services\Audit\AuditChainService;
use App\Services\System\CacheKeys;
use App\Services\System\CacheOptimizationService;
use App\Support\ActorContext;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function __construct(
        protected ?CacheOptimizationService $cacheOptimizationService = null,
        protected ?AuditChainService $auditChainService = null,
    ) {
        $this->cacheOptimizationService ??= new CacheOptimizationService;
        $this->auditChainService ??= new AuditChainService;
    }

    /**
     * Severity maps per action, keyed by action prefix used by logAction().
     *
     * Each entry maps a specific action name to its severity. The special
     * '*' key is the default for any action in that domain not explicitly listed.
     *
     * @var array<string, array<string, SystemLogSeverity>>
     */
    private const SEVERITY_MAPS = [
        'compliance_flag_' => [
            'compliance_flag_assigned' => SystemLogSeverity::Warning,
            'compliance_flag_resolved' => SystemLogSeverity::Info,
            '*' => SystemLogSeverity::Info,
        ],
        'compliance_alert_' => [
            'compliance_alert_created' => SystemLogSeverity::Warning,
            'compliance_alert_escalated' => SystemLogSeverity::Warning,
            'compliance_alert_bulk_dismissed' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'compliance_case_' => [
            'compliance_case_priority_changed' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'stock_transfer_' => [
            'stock_transfer_partially_received' => SystemLogSeverity::Warning,
            'stock_transfer_cancelled' => SystemLogSeverity::Warning,
            'stock_transfer_variance_exceeded' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'journal_entry_' => [
            'journal_entry_rejected' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'position_' => [
            'position_limit_breach' => SystemLogSeverity::Warning,
            'position_manual_adjustment' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'customer_risk_' => [
            'customer_risk_level_upgraded' => SystemLogSeverity::Warning,
            'customer_risk_locked' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'sanction_' => [
            'sanction_screening_hit' => SystemLogSeverity::Error,
            'sanction_manual_override' => SystemLogSeverity::Warning,
            'sanction_block_overridden' => SystemLogSeverity::Critical,
            '*' => SystemLogSeverity::Info,
        ],
        'mfa_' => [
            'mfa_verification_failed' => SystemLogSeverity::Warning,
            'mfa_disable_requested' => SystemLogSeverity::Warning,
            'mfa_recovery_code_used' => SystemLogSeverity::Warning,
            'mfa_trusted_device_removed' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'session_' => [
            'session_concurrent_blocked' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'regulatory_report_' => [
            'regulatory_report_submitted' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'report_' => [
            'report_audit_log_viewed' => SystemLogSeverity::Warning,
            'report_data_export' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'edd_template_' => [
            'edd_template_deleted' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'api_' => [
            'api_login_failed' => SystemLogSeverity::Warning,
            '*' => SystemLogSeverity::Info,
        ],
        'aml_' => [
            'aml_velocity_alert_triggered' => SystemLogSeverity::Error,
            'aml_structuring_detected' => SystemLogSeverity::Error,
            'aml_rule_triggered' => SystemLogSeverity::Error,
            '*' => SystemLogSeverity::Info,
        ],
    ];

    /**
     * Resolve the severity for an action using the data-driven severity maps.
     * Supports prefix matching (e.g. 'compliance_alert_' => 'compliance_alert_created')
     * to keep the maps compact while handling arbitrarily many action names.
     */
    private function resolveSeverity(string $action, SystemLogSeverity $domainDefault = SystemLogSeverity::Info): SystemLogSeverity
    {
        foreach (self::SEVERITY_MAPS as $prefix => $map) {
            if (str_starts_with($action, $prefix)) {
                if (isset($map[$action])) {
                    return $map[$action];
                }

                return $map['*'];
            }
        }

        return $domainDefault;
    }

    /**
     * Unified audit logging entry point (replacement for all Audits* concern traits).
     *
     * All domain-specific logging methods (logComplianceDecision, logMfaEvent,
     * logStockTransferEvent, etc.) delegate to this single method. Callers
     * outside this class should continue using the domain-specific wrappers.
     *
     * @param  string  $action  The audit action name (e.g. 'compliance_alert_created')
     * @param  string  $entityType  Entity type (e.g. 'Alert', 'Customer', 'StockTransfer')
     * @param  int|null  $entityId  Entity ID, or null
     * @param  array  $data  Old/new values and any extra keys to propagate
     * @param  SystemLogSeverity|string  $severity  Severity level to override automatic resolution
     */
    public function logAction(
        string $action,
        string $entityType,
        ?int $entityId,
        array $data = [],
        SystemLogSeverity|string $severity = ''
    ): SystemLog {
        $resolvedSeverity = $severity !== ''
            ? $severity
            : $this->resolveSeverity($action);

        // Normalize old/new aliases to old_values/new_values and drop the raw keys
        // so only extra keys (user_id, ip_address, etc.) survive the merge.
        $extra = $data;
        unset($extra['old'], $extra['new'], $extra['old_values'], $extra['new_values']);

        return $this->logWithSeverity($action, array_merge([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $data['old_values'] ?? $data['old'] ?? [],
            'new_values' => $data['new_values'] ?? $data['new'] ?? [],
        ], $extra), $resolvedSeverity);
    }

    /**
     * Single audit writer shared by the sealed and unsealed entry points.
     * Resolves user/IP context, normalizes severity, creates the
     * system_logs row, and mirrors the event into audit_trails so every
     * audited entity is queryable there regardless of sealing mode (D2).
     * system_logs remains the canonical, tamper-evident store; the mirror
     * is best-effort so a mirror write failure can never compromise the
     * canonical chain.
     *
     * @param  array<string, mixed>  $data
     */
    private function createLogEntry(string $action, array $data, SystemLogSeverity|string $severity): SystemLog
    {
        $userId = array_key_exists('user_id', $data) ? $data['user_id'] : ActorContext::capture()->userId;
        $ipAddress = array_key_exists('ip_address', $data) ? $data['ip_address'] : Request::ip();

        // system_logs.severity is an uppercase enum (INFO/WARNING/ERROR/CRITICAL);
        // normalize so legacy lowercase call sites cannot trip the CHECK constraint,
        // and resolve through the enum so unknown severities fail fast here.
        $severity = ($severity instanceof SystemLogSeverity ? $severity : SystemLogSeverity::normalize($severity))->value;

        $log = SystemLog::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $data['description'] ?? null,
            'severity' => $severity,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_values' => ! empty($data['old_values'] ?? []) ? $data['old_values'] : null,
            'new_values' => ! empty($data['new_values'] ?? []) ? $data['new_values'] : null,
            'ip_address' => $ipAddress,
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);

        try {
            if (($data['entity_type'] ?? null) !== null && ($data['entity_id'] ?? null) !== null) {
                AuditTrail::create([
                    'auditable_type' => $data['entity_type'],
                    'auditable_id' => $data['entity_id'],
                    'action' => $action,
                    'user_id' => $userId,
                    'metadata' => [
                        'old' => $data['old_values'] ?? [],
                        'new' => $data['new_values'] ?? [],
                    ],
                    'ip_address' => $ipAddress,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('audit_trails mirror write failed', [
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Log with severity level (tamper-evident with hash chaining).
     */
    public function logWithSeverity(
        string $action,
        array $data = [],
        SystemLogSeverity|string $severity = SystemLogSeverity::Info
    ): SystemLog {
        $log = $this->createLogEntry($action, $data, $severity);

        try {
            SealAuditHashJob::dispatch($log->id);
        } catch (\Throwable $e) {
            // On synchronous queue drivers the seal job executes inline, so a
            // transient seal failure (e.g. an unsealed predecessor gap) would
            // otherwise propagate and abort the business operation that merely
            // wrote an audit entry. Queued drivers already isolate job
            // failures; mirror that here — the entry exists and is sealed by
            // the retry ladder or the audit:seal-pending sweeper.
            Log::warning('SealAuditHashJob inline dispatch failed', [
                'log_id' => $log->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Log with severity level and synchronously seal the hash chain.
     */
    public function logWithSeveritySealed(
        string $action,
        array $data = [],
        SystemLogSeverity|string $severity = SystemLogSeverity::Info
    ): SystemLog {
        $log = $this->createLogEntry($action, $data, $severity);

        if ($log->severity === SystemLogSeverity::Critical) {
            $sealed = false;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $sealed = $this->auditChainService->sealLogEntry($log->id);

                if ($sealed) {
                    break;
                }

                // A concurrent writer likely holds an unsealed predecessor;
                // back off briefly to let it finish instead of retrying in a
                // tight loop.
                if ($attempt < $maxAttempts) {
                    usleep(50000 * $attempt); // 50ms, then 100ms
                }
            }

            if (! $sealed) {
                // CRITICAL business operations must not abort because an
                // unrelated entry was momentarily unsealed: fall back to
                // asynchronous sealing on the audit queue.
                Log::warning(
                    "Failed to synchronously seal audit log entry {$log->id} after {$maxAttempts} attempts; deferred to SealAuditHashJob.",
                    ['log_id' => $log->id]
                );

                try {
                    SealAuditHashJob::dispatch($log->id)->onQueue('audit');
                } catch (\Throwable $e) {
                    Log::warning('SealAuditHashJob inline dispatch failed', [
                        'log_id' => $log->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            try {
                SealAuditHashJob::dispatch($log->id)->onQueue('audit');
            } catch (\Throwable $e) {
                Log::warning('SealAuditHashJob inline dispatch failed', [
                    'log_id' => $log->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $log->fresh();
    }

    /**
     * Log standard action.
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $oldValues = [],
        array $newValues = []
    ): SystemLog {
        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $userId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
            SystemLogSeverity::Info
        );
    }

    /**
     * Build a payload for a transaction entity log, forwarding explicit user/IP when provided.
     */
    private function transactionPayload(int $transactionId, array $data): array
    {
        return $this->entityPayload('Transaction', $transactionId, $data);
    }

    /**
     * Build a payload for a customer entity log, forwarding explicit user/IP when provided.
     */
    private function customerPayload(int $customerId, array $data): array
    {
        return $this->entityPayload('Customer', $customerId, $data);
    }

    /**
     * Build a generic entity payload, forwarding explicit user_id and ip_address when provided.
     */
    private function entityPayload(string $entityType, int $entityId, array $data): array
    {
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $data['old'] ?? [],
            'new_values' => $data['new'] ?? [],
        ];

        if (array_key_exists('user_id', $data)) {
            $payload['user_id'] = $data['user_id'];
        }

        if (array_key_exists('ip_address', $data)) {
            $payload['ip_address'] = $data['ip_address'];
        }

        return $payload;
    }

    /* ---------------------------------------------------------------------
     * Domain-specific convenience wrappers — all delegate to logAction().
     * Kept for backward compatibility with every existing caller across
     * services, controllers, listeners, and jobs.
     * -------------------------------------------------------------------- */

    public function logTransaction(string $action, int $transactionId, array $data = []): SystemLog
    {
        return $this->logWithSeverity($action, $this->transactionPayload($transactionId, $data), $data['severity'] ?? SystemLogSeverity::Info);
    }

    public function logTransactionSealed(string $action, int $transactionId, array $data = []): SystemLog
    {
        return $this->logWithSeveritySealed($action, $this->transactionPayload($transactionId, $data), $data['severity'] ?? SystemLogSeverity::Info);
    }

    public function logCustomer(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logWithSeverity($action, $this->customerPayload($customerId, $data), $data['severity'] ?? SystemLogSeverity::Info);
    }

    public function logCustomerSealed(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logWithSeveritySealed($action, $this->customerPayload($customerId, $data), $data['severity'] ?? SystemLogSeverity::Info);
    }

    public function logComplianceDecision(string $action, int $entityId, array $data = [], SystemLogSeverity|string $severity = SystemLogSeverity::Info): SystemLog
    {
        return $this->logAction($action, $data['entity_type'] ?? 'Compliance', $entityId, [
            'old_values' => $data['old'] ?? [],
            'new_values' => $data['new'] ?? [],
        ], $severity);
    }

    public function logCddDecision(int $transactionId, string $cddLevel, array $triggers = []): SystemLog
    {
        return $this->logAction('cdd_decision', 'Transaction', $transactionId, [
            'new_values' => ['cdd_level' => $cddLevel, 'triggers' => $triggers],
        ]);
    }

    public function logComplianceAlertEvent(string $action, int $alertId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'Alert', $alertId, $data);
    }

    public function logComplianceCaseEvent(string $action, int $caseId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'ComplianceCase', $caseId, $data);
    }

    public function logAmlMonitorEvent(string $action, ?int $entityId = null, array $data = []): SystemLog
    {
        return $this->logAction($action, $data['entity_type'] ?? 'AmlMonitor', $entityId, $data);
    }

    public function logStockTransferEvent(string $action, int $transferId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'StockTransfer', $transferId, $data);
    }

    public function logJournalWorkflowEvent(string $action, int $entryId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'JournalEntry', $entryId, $data);
    }

    public function logPositionEvent(string $action, array $data = []): SystemLog
    {
        return $this->logAction($action, 'CurrencyPosition', $data['position_id'] ?? null, $data);
    }

    public function logTransactionWorkflow(string $step, int $transactionId, string $status, array $context = []): SystemLog
    {
        return $this->logWithSeverity($step, [
            'entity_type' => 'Transaction',
            'entity_id' => $transactionId,
            'new_values' => $context,
        ], $status === 'ERROR' ? SystemLogSeverity::Error : SystemLogSeverity::Info);
    }

    public function logCustomerRiskEvent(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'Customer', $customerId, $data);
    }

    public function logCustomerEvent(string $action, int $customerId, array $data = [], SystemLogSeverity|string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'Customer', $customerId, $data, $severity);
    }

    public function logEmergencyClosureEvent(string $action, int $closureId, array $data = [], SystemLogSeverity|string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'EmergencyClosure', $closureId, $data, $severity);
    }

    public function logFlaggedTransactionEvent(string $action, int $entityId, array $data = [], SystemLogSeverity|string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'FlaggedTransaction', $entityId, $data, $severity);
    }

    public function logPreTransactionEvent(string $action, int $entityId, array $data = [], SystemLogSeverity|string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'PreTransaction', $entityId, $data, $severity);
    }

    public function logSanctionEvent(string $action, ?int $entityId = null, array $data = []): SystemLog
    {
        return $this->logAction($action, $data['entity_type'] ?? 'Sanction', $entityId, $data);
    }

    public function logMfaEvent(string $action, ?int $userId = null, array $data = []): SystemLog
    {
        return $this->logAction($action, 'MfaEvent', $data['entity_id'] ?? null, [
            'user_id' => $userId ?? ActorContext::capture()->userId,
            'old_values' => $data['old'] ?? [],
            'new_values' => $data['new'] ?? [],
        ]);
    }

    public function logSessionEvent(string $action, array $data = []): SystemLog
    {
        return $this->logAction($action, 'Session', $data['session_id'] ?? null, $data);
    }

    public function logPermissionDenied(string $resource, string $action, string $reason, array $data = []): SystemLog
    {
        return $this->logWithSeverity('permission_denied', [
            'user_id' => ActorContext::capture()->userId,
            'entity_type' => $resource,
            'entity_id' => $data['entity_id'] ?? null,
            'new_values' => [
                'action' => $action,
                'reason' => $reason,
                'resource' => $resource,
                'attempted_at' => now()->toIso8601String(),
            ],
        ], SystemLogSeverity::Warning);
    }

    public function logRegulatoryReportEvent(string $action, int $reportId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'ReportGenerated', $reportId, $data);
    }

    public function logReportAccessEvent(string $action, array $data = []): SystemLog
    {
        return $this->logAction($action, $data['entity_type'] ?? 'Report', $data['entity_id'] ?? null, $data);
    }

    public function logEddTemplateEvent(string $action, int $templateId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'EddTemplate', $templateId, $data);
    }

    public function logApiAccessEvent(string $action, array $data = []): SystemLog
    {
        return $this->logWithSeverity($action, [
            'user_id' => $data['user_id'] ?? ActorContext::capture()->userId,
            'entity_type' => 'ApiAccess',
            'entity_id' => $data['entity_id'] ?? null,
            'new_values' => $data['new'] ?? [],
        ], $this->resolveSeverity($action));
    }

    public function logBranchAccessEvent(int $accessedBranchId, string $resource, int $resourceId, array $data = []): SystemLog
    {
        return $this->logWithSeverity('cross_branch_access', [
            'user_id' => ActorContext::capture()->userId,
            'entity_type' => $resource,
            'entity_id' => $resourceId,
            'new_values' => [
                'accessed_branch_id' => $accessedBranchId,
                'accessed_branch_name' => $data['branch_name'] ?? null,
                'user_branch_id' => ActorContext::capture()->user?->branch_id,
            ],
        ], SystemLogSeverity::Warning);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logBranchEvent(string $action, int $branchId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'Branch', $branchId, $data);
    }

    public function logBatchOperationEvent(string $action, array $data = []): SystemLog
    {
        return $this->logWithSeverity($action, [
            'user_id' => ActorContext::capture()->userId,
            'entity_type' => 'BatchOperation',
            'entity_id' => $data['batch_id'] ?? null,
            'new_values' => [
                'items_processed' => $data['items_processed'] ?? 0,
                'items_succeeded' => $data['items_succeeded'] ?? 0,
                'items_failed' => $data['items_failed'] ?? 0,
            ],
        ], SystemLogSeverity::Info);
    }

    public function logProcedureTrigger(string $procedureName, array $parameters = []): SystemLog
    {
        return $this->logWithSeverity('procedure_triggered', [
            'entity_type' => 'Procedure',
            'entity_id' => null,
            'new_values' => [
                'procedure_name' => $procedureName,
                'parameters' => $parameters,
            ],
        ], SystemLogSeverity::Info);
    }

    public function logControllerAction(
        string $controller,
        string $action,
        ?int $userId,
        array $requestData = [],
        array $result = []
    ): SystemLog {
        return $this->logWithSeverity($action, [
            'user_id' => $userId,
            'entity_type' => $controller,
            'new_values' => [
                'request_data' => $requestData,
                'result' => $result,
            ],
        ], SystemLogSeverity::Info);
    }

    public function logModelEvent(
        string $model,
        string $event,
        ?int $modelId,
        array $changes = [],
        array $original = []
    ): SystemLog {
        return $this->logWithSeverity(strtoupper($event), [
            'entity_type' => $model,
            'entity_id' => $modelId,
            'old_values' => $original,
            'new_values' => $changes,
        ], SystemLogSeverity::Info);
    }

    /**
     * Distinct SystemLog values for a column containing any of the given
     * substrings (case-insensitive). The distinct list rides a covering index
     * and is cached; new values become matchable within the TTL.
     *
     * @param  list<string>  $needles
     * @return list<string>
     */
    public function matchingLogValues(string $column, array $needles): array
    {
        $values = $this->cacheOptimizationService->remember(
            CacheKeys::auditLogDistinct($column),
            300,
            ['audit'],
            fn () => SystemLog::distinct()->pluck($column)->all()
        );

        return array_values(array_filter(
            $values,
            fn (string $value) => collect($needles)->contains(
                fn (string $needle) => str_contains(mb_strtolower($value), mb_strtolower($needle))
            )
        ));
    }

    /**
     * Batch insert multiple audit log entries.
     */
    public function logBatch(array $logs): bool
    {
        if (empty($logs)) {
            return true;
        }

        $now = now();
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();
        $sessionId = session()->getId();

        $batchData = array_map(function ($log) use ($now, $ipAddress, $userAgent, $sessionId) {
            return [
                'user_id' => $log['user_id'] ?? ActorContext::capture()->userId,
                'action' => $log['action'],
                'severity' => ($log['severity'] instanceof SystemLogSeverity ? $log['severity'] : SystemLogSeverity::normalize($log['severity'] ?? 'INFO'))->value,
                'entity_type' => $log['entity_type'] ?? null,
                'entity_id' => $log['entity_id'] ?? null,
                'old_values' => ! empty($log['old_values'] ?? []) ? $log['old_values'] : null,
                'new_values' => ! empty($log['new_values'] ?? []) ? $log['new_values'] : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'previous_hash' => null,
                'entry_hash' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $logs);

        $inserted = SystemLog::insert($batchData);

        if ($inserted) {
            // Single insert() yields a contiguous ID range. Capture the range
            // via the DB connection's lastInsertId to avoid concurrent-insert
            // contamination (the prior max(id) - count math broke under
            // concurrent writes).
            $lastId = (int) DB::getPdo()->lastInsertId();
            $count = count($logs);
            $firstId = $lastId - $count + 1;

            $chunks = collect(range($firstId, $lastId))->chunk(100);
            foreach ($chunks as $chunk) {
                Bus::batch(
                    $chunk->map(fn ($id) => new SealAuditHashJob($id))->toArray()
                )->dispatch();
            }
        }

        return $inserted;
    }
}
