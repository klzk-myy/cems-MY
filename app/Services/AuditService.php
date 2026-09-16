<?php

namespace App\Services;

use App\Exceptions\Domain\AuditIntegrityException;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\AuditTrail;
use App\Models\SystemLog;
use App\Services\Contracts\AuditServiceInterface;
use App\Support\ActorContext;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditService implements AuditServiceInterface
{
    /**
     * Hash algorithm versions are encoded inside entry_hash itself (no schema
     * change): v1 rows store a bare 64-char SHA-256 of the legacy metadata-only
     * payload; v2 rows store 'v2:<sha256>' where the payload additionally covers
     * old_values, new_values, severity and ip_address as canonical JSON. New
     * seals use v2; existing sealed rows keep verifying under the v1 formula.
     */
    private const HASH_V2_PREFIX = 'v2:';

    /**
     * Severity maps per action, keyed by action prefix used by logAction().
     *
     * Each entry maps a specific action name to its severity. The special
     * '*' key is the default for any action in that domain not explicitly listed.
     */
    /**
     * @var array<string, array<string, string>>
     */
    private const SEVERITY_MAPS = [
        'compliance_flag_' => [
            'compliance_flag_assigned' => 'WARNING',
            'compliance_flag_resolved' => 'INFO',
            '*' => 'INFO',
        ],
        'compliance_alert_' => [
            'compliance_alert_created' => 'WARNING',
            'compliance_alert_escalated' => 'WARNING',
            'compliance_alert_bulk_dismissed' => 'WARNING',
            '*' => 'INFO',
        ],
        'compliance_case_' => [
            'compliance_case_priority_changed' => 'WARNING',
            '*' => 'INFO',
        ],
        'stock_transfer_' => [
            'stock_transfer_partially_received' => 'WARNING',
            'stock_transfer_cancelled' => 'WARNING',
            'stock_transfer_variance_exceeded' => 'WARNING',
            '*' => 'INFO',
        ],
        'journal_entry_' => [
            'journal_entry_rejected' => 'WARNING',
            '*' => 'INFO',
        ],
        'position_' => [
            'position_limit_breach' => 'WARNING',
            'position_manual_adjustment' => 'WARNING',
            '*' => 'INFO',
        ],
        'customer_risk_' => [
            'customer_risk_level_upgraded' => 'WARNING',
            'customer_risk_locked' => 'WARNING',
            '*' => 'INFO',
        ],
        'sanction_' => [
            'sanction_screening_hit' => 'ERROR',
            'sanction_manual_override' => 'WARNING',
            'sanction_block_overridden' => 'CRITICAL',
            '*' => 'INFO',
        ],
        'mfa_' => [
            'mfa_verification_failed' => 'WARNING',
            'mfa_disable_requested' => 'WARNING',
            'mfa_recovery_code_used' => 'WARNING',
            'mfa_trusted_device_removed' => 'WARNING',
            '*' => 'INFO',
        ],
        'session_' => [
            'session_concurrent_blocked' => 'WARNING',
            '*' => 'INFO',
        ],
        'regulatory_report_' => [
            'regulatory_report_submitted' => 'WARNING',
            '*' => 'INFO',
        ],
        'report_' => [
            'report_audit_log_viewed' => 'WARNING',
            'report_data_export' => 'WARNING',
            '*' => 'INFO',
        ],
        'edd_template_' => [
            'edd_template_deleted' => 'WARNING',
            '*' => 'INFO',
        ],
        'api_' => [
            'api_login_failed' => 'WARNING',
            '*' => 'INFO',
        ],
        'aml_' => [
            'aml_velocity_alert_triggered' => 'ERROR',
            'aml_structuring_detected' => 'ERROR',
            'aml_rule_triggered' => 'ERROR',
            '*' => 'INFO',
        ],
    ];

    /**
     * Resolve the severity for an action using the data-driven severity maps.
     * Supports prefix matching (e.g. 'compliance_alert_' => 'compliance_alert_created')
     * to keep the maps compact while handling arbitrarily many action names.
     */
    private function resolveSeverity(string $action, string $domainDefault = 'INFO'): string
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
     * @param  string  $severity  Severity level to override automatic resolution
     */
    public function logAction(
        string $action,
        string $entityType,
        ?int $entityId,
        array $data = [],
        string $severity = ''
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
     * Compute SHA-256 hash for a log entry (tamper-evident chain).
     *
     * Versioned: when only the six legacy arguments are provided the bare v1
     * hash is returned (metadata fields only — used by SealAuditHashJob and
     * for verifying historical rows). When row-payload context (old_values,
     * new_values, severity, ip_address) is supplied, a 'v2:'-prefixed hash
     * covering that payload is returned so sealed rows cannot be silently
     * edited. verifyChainIntegrity() branches on the stored prefix.
     */
    public function computeEntryHash(
        string $timestamp,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $previousHash,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $severity = null,
        ?string $ipAddress = null
    ): string {
        // Legacy v1 payload: metadata fields only.
        $data = implode('|', [
            $timestamp,
            (string) $userId,
            $action,
            $entityType ?? '',
            $entityId !== null ? (string) $entityId : '',
            $previousHash ?? '',
        ]);

        if ($oldValues === null && $newValues === null && $severity === null && $ipAddress === null) {
            return hash('sha256', $data);
        }

        // v2 payload additionally covers the row data excluded from v1.
        $payload = implode('|', [
            $data,
            $this->canonicalJson($oldValues ?? []),
            $this->canonicalJson($newValues ?? []),
            $severity ?? '',
            $ipAddress ?? '',
        ]);

        return self::HASH_V2_PREFIX.hash('sha256', $payload);
    }

    /**
     * Deterministic JSON encoding for hashed payloads: keys sorted at every
     * nesting depth (not just top level) and unicode/slashes unescaped so
     * byte representation is stable across seal and verification time,
     * regardless of nested-array insertion order.
     */
    private function canonicalJson(array $values): string
    {
        $canonicalize = static function (array $data) use (&$canonicalize): array {
            ksort($data);

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $canonicalize($value);
                }
            }

            return $data;
        };

        $encoded = json_encode(
            $canonicalize($values),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Synchronously seal a single audit log entry's hash chain.
     */
    public function sealLogEntry(int $logId): bool
    {
        return DB::transaction(function () use ($logId) {
            $predecessorId = SystemLog::where('id', '<', $logId)
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->value('id');

            $predecessor = null;
            if ($predecessorId) {
                $predecessor = SystemLog::where('id', $predecessorId)->lockForUpdate()->first();

                if (! $predecessor) {
                    throw new AuditIntegrityException("Predecessor log {$predecessorId} disappeared.");
                }

                $unsealedBetween = SystemLog::where('id', '>', $predecessorId)
                    ->where('id', '<', $logId)
                    ->whereNull('entry_hash')
                    ->exists();

                if ($unsealedBetween) {
                    return false;
                }
            }

            $log = SystemLog::where('id', $logId)
                ->whereNull('entry_hash')
                ->lockForUpdate()
                ->first();

            if (! $log) {
                return true;
            }

            $previousHash = $predecessor->entry_hash ?? null;

            // Seal with the v2 formula: the hash covers old_values,
            // new_values, severity and ip_address so post-seal payload edits
            // no longer verify clean.
            $entryHash = $this->computeEntryHash(
                $log->created_at->toIso8601String(),
                $log->user_id,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $previousHash,
                $log->old_values,
                $log->new_values,
                $log->severity,
                $log->ip_address
            );

            $log->update([
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
            ]);

            return true;
        });
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
    private function createLogEntry(string $action, array $data, string $severity): SystemLog
    {
        $userId = array_key_exists('user_id', $data) ? $data['user_id'] : ActorContext::capture()->userId;
        $ipAddress = array_key_exists('ip_address', $data) ? $data['ip_address'] : Request::ip();

        // system_logs.severity is an uppercase enum (INFO/WARNING/ERROR/CRITICAL);
        // normalize so legacy lowercase call sites cannot trip the CHECK constraint.
        $severity = strtoupper($severity);

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
        string $severity = 'INFO'
    ): SystemLog {
        $log = $this->createLogEntry($action, $data, $severity);

        SealAuditHashJob::dispatch($log->id);

        return $log;
    }

    /**
     * Log with severity level and synchronously seal the hash chain.
     */
    public function logWithSeveritySealed(
        string $action,
        array $data = [],
        string $severity = 'INFO'
    ): SystemLog {
        $log = $this->createLogEntry($action, $data, $severity);

        if (in_array($log->severity, ['CRITICAL'], true)) {
            $sealed = false;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $sealed = $this->sealLogEntry($log->id);

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

                SealAuditHashJob::dispatch($log->id)->onQueue('audit');
            }
        } else {
            SealAuditHashJob::dispatch($log->id)->onQueue('audit');
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
            'INFO'
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
        return $this->logWithSeverity($action, $this->transactionPayload($transactionId, $data), $data['severity'] ?? 'INFO');
    }

    public function logTransactionSealed(string $action, int $transactionId, array $data = []): SystemLog
    {
        return $this->logWithSeveritySealed($action, $this->transactionPayload($transactionId, $data), $data['severity'] ?? 'INFO');
    }

    public function logCustomer(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logWithSeverity($action, $this->customerPayload($customerId, $data), $data['severity'] ?? 'INFO');
    }

    public function logCustomerSealed(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logWithSeveritySealed($action, $this->customerPayload($customerId, $data), $data['severity'] ?? 'INFO');
    }

    public function logComplianceDecision(string $action, int $entityId, array $data = [], string $severity = 'INFO'): SystemLog
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
        ], $status === 'ERROR' ? 'ERROR' : 'INFO');
    }

    public function logCustomerRiskEvent(string $action, int $customerId, array $data = []): SystemLog
    {
        return $this->logAction($action, 'Customer', $customerId, $data);
    }

    public function logCustomerEvent(string $action, int $customerId, array $data = [], string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'Customer', $customerId, $data, $severity);
    }

    public function logEmergencyClosureEvent(string $action, int $closureId, array $data = [], string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'EmergencyClosure', $closureId, $data, $severity);
    }

    public function logFlaggedTransactionEvent(string $action, int $entityId, array $data = [], string $severity = ''): SystemLog
    {
        return $this->logAction($action, 'FlaggedTransaction', $entityId, $data, $severity);
    }

    public function logPreTransactionEvent(string $action, int $entityId, array $data = [], string $severity = ''): SystemLog
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
        ], 'WARNING');
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
        ], 'WARNING');
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
        ], 'INFO');
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
        ], 'INFO');
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
        ], 'INFO');
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
        ], 'INFO');
    }

    /**
     * Verify the integrity of the audit log chain.
     *
     * Each entry is recomputed with the formula matching its stored hash
     * version: 'v2:'-prefixed hashes verify against the v2 payload formula,
     * bare hashes against the legacy v1 metadata-only formula.
     */
    public function verifyChainIntegrity(?int $limit = null): array
    {
        $previousHash = null;
        $checked = 0;
        $broken = null;
        $isFirstEntryInWindow = true;

        $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

        if ($limit !== null) {
            $lastIds = SystemLog::whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->pluck('id');

            $query->whereIn('id', $lastIds);
        }

        $query->chunkById(1000, function ($entries) use (&$previousHash, &$checked, &$broken, &$isFirstEntryInWindow) {
            foreach ($entries as $entry) {
                // The oldest entry in a limited window links to a predecessor
                // outside the window, so seed the expectation from its stored
                // previous_hash: skip the link check for the first entry and
                // compare strictly from the second one onward.
                if (! $isFirstEntryInWindow
                    && ! hash_equals((string) $previousHash, (string) $entry->previous_hash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Previous hash mismatch.'];

                    return false;
                }
                $isFirstEntryInWindow = false;

                $storedHash = (string) $entry->entry_hash;

                if (str_starts_with($storedHash, self::HASH_V2_PREFIX)) {
                    // v2: recompute with full row payload included.
                    $recomputedHash = $this->computeEntryHash(
                        $entry->created_at->toIso8601String(),
                        $entry->user_id,
                        $entry->action,
                        $entry->entity_type,
                        $entry->entity_id,
                        $entry->previous_hash,
                        $entry->old_values,
                        $entry->new_values,
                        $entry->severity,
                        $entry->ip_address
                    );
                } else {
                    // v1 (legacy): metadata-only payload.
                    $recomputedHash = $this->computeEntryHash(
                        $entry->created_at->toIso8601String(),
                        $entry->user_id,
                        $entry->action,
                        $entry->entity_type,
                        $entry->entity_id,
                        $entry->previous_hash
                    );
                }

                if (! hash_equals($recomputedHash, $storedHash)) {
                    $broken = ['valid' => false, 'broken_at' => $entry->id, 'message' => 'Entry hash mismatch.'];

                    return false;
                }

                $previousHash = $storedHash;
                $checked++;
            }
        });

        if ($broken) {
            return $broken;
        }

        return [
            'valid' => true,
            'broken_at' => null,
            'message' => "Chain integrity verified: {$checked} entries checked.",
        ];
    }

    public function getUnsealedCount(): int
    {
        return SystemLog::whereNull('entry_hash')->count();
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
                'severity' => $log['severity'] ?? 'INFO',
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
