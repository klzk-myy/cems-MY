<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Compute SHA-256 hash for a log entry (tamper-evident chain)
     *
     * Each log entry's hash is computed from:
     * - timestamp (created_at)
     * - user_id
     * - action
     * - entity_type
     * - entity_id
     * - previous_hash (chain link to prior entry)
     */
    public function computeEntryHash(
        string $timestamp,
        ?int $userId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?string $previousHash
    ): string {
        $data = implode('|', [
            $timestamp,
            (string) $userId,
            $action,
            $entityType ?? '',
            $entityId !== null ? (string) $entityId : '',
            $previousHash ?? '',
        ]);

        return hash('sha256', $data);
    }

    /**
     * Log with severity level (tamper-evident with hash chaining)
     *
     * Hash sealing is done asynchronously via SealAuditHashJob to avoid
     * global lock contention. The entry is created with null hash values
     * and sealed by the queued job.
     */
    public function logWithSeverity(
        string $action,
        array $data = [],
        string $severity = 'INFO'
    ): SystemLog {
        $userId = $data['user_id'] ?? auth()->id();

        // Create log entry with null hash (will be sealed async)
        $log = SystemLog::create([
            'user_id' => $userId,
            'action' => $action,
            'severity' => $severity,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_values' => ! empty($data['old_values'] ?? []) ? $data['old_values'] : null,
            'new_values' => ! empty($data['new_values'] ?? []) ? $data['new_values'] : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);

        // Dispatch async job to seal the hash chain
        \App\Jobs\Audit\SealAuditHashJob::dispatch($log->id);

        return $log;
    }

    /**
     * Log standard action
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
     * Log transaction action
     */
    public function logTransaction(
        string $action,
        int $transactionId,
        array $data = []
    ): SystemLog {
        $severity = $data['severity'] ?? 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Transaction',
                'entity_id' => $transactionId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log customer action
     */
    public function logCustomer(
        string $action,
        int $customerId,
        array $data = []
    ): SystemLog {
        $severity = $data['severity'] ?? 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Customer',
                'entity_id' => $customerId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log STR (Suspicious Transaction Report) action for compliance audit trail.
     *
     * @param  string  $action  STR action (str_created, str_submitted, str_approved, etc.)
     * @param  int  $strId  STR Report ID
     * @param  array  $data  Additional data
     */
    public function logStrAction(string $action, int $strId, array $data = []): SystemLog
    {
        $severity = $data['severity'] ?? 'WARNING';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'StrReport',
                'entity_id' => $strId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log compliance decision action (flag resolved, EDD decision, etc.).
     *
     * @param  string  $action  Action type
     * @param  int  $entityId  Entity ID (flag ID, transaction ID, etc.)
     * @param  array  $data  Decision data including old/new values
     * @param  string  $severity  Log severity level
     */
    public function logComplianceDecision(string $action, int $entityId, array $data = [], string $severity = 'INFO'): SystemLog
    {
        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => $data['entity_type'] ?? 'Compliance',
                'entity_id' => $entityId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log CDD/EDD decision for a transaction.
     *
     * @param  int  $transactionId  Transaction ID
     * @param  string  $cddLevel  CDD level determined
     * @param  array  $triggers  What triggered the CDD level
     */
    public function logCddDecision(int $transactionId, string $cddLevel, array $triggers = []): SystemLog
    {
        return $this->logWithSeverity(
            'cdd_decision',
            [
                'entity_type' => 'Transaction',
                'entity_id' => $transactionId,
                'new_values' => [
                    'cdd_level' => $cddLevel,
                    'triggers' => $triggers,
                ],
            ],
            'INFO'
        );
    }

    /**
     * Log MFA (Multi-Factor Authentication) events.
     *
     * @param  string  $action  MFA action (mfa_setup_started, mfa_setup_completed,
     *                          mfa_verification_success, mfa_verification_failed,
     *                          mfa_disable_requested, mfa_disable_completed,
     *                          mfa_recovery_code_used, mfa_trusted_device_added,
     *                          mfa_trusted_device_removed)
     * @param  int|null  $userId  User ID (null if not authenticated)
     * @param  array  $data  Additional context data
     */
    public function logMfaEvent(string $action, ?int $userId = null, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'mfa_verification_failed', 'mfa_disable_requested', 'mfa_recovery_code_used',
            'mfa_trusted_device_removed' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $userId ?? auth()->id(),
                'entity_type' => 'MfaEvent',
                'entity_id' => $data['entity_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log stock transfer events.
     *
     * @param  string  $action  Transfer action (stock_transfer_created,
     *                          stock_transfer_approved_bm, stock_transfer_approved_hq,
     *                          stock_transfer_dispatched, stock_transfer_partially_received,
     *                          stock_transfer_completed, stock_transfer_cancelled,
     *                          stock_transfer_variance_exceeded)
     * @param  int  $transferId  Stock transfer ID
     * @param  array  $data  Transfer data with old/new values
     */
    public function logStockTransferEvent(string $action, int $transferId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'stock_transfer_partially_received', 'stock_transfer_cancelled',
            'stock_transfer_variance_exceeded' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'StockTransfer',
                'entity_id' => $transferId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log journal entry workflow events.
     *
     * @param  string  $action  Workflow action (journal_entry_submitted,
     *                          journal_entry_approved, journal_entry_rejected)
     * @param  int  $entryId  Journal entry ID
     * @param  array  $data  Workflow data
     */
    public function logJournalWorkflowEvent(string $action, int $entryId, array $data = []): SystemLog
    {
        $severity = $action === 'journal_entry_rejected' ? 'WARNING' : 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'JournalEntry',
                'entity_id' => $entryId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log compliance alert events.
     *
     * @param  string  $action  Alert action (compliance_alert_created,
     *                          compliance_alert_triaged, compliance_alert_assigned,
     *                          compliance_alert_dismissed, compliance_alert_escalated,
     *                          compliance_alert_resolved, compliance_alert_bulk_dismissed)
     * @param  int  $alertId  Alert ID
     * @param  array  $data  Alert data
     */
    public function logComplianceAlertEvent(string $action, int $alertId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'compliance_alert_created', 'compliance_alert_escalated' => 'WARNING',
            'compliance_alert_bulk_dismissed' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Alert',
                'entity_id' => $alertId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log compliance case events.
     *
     * @param  string  $action  Case action (compliance_case_created,
     *                          compliance_case_status_changed, compliance_case_assigned,
     *                          compliance_case_note_added, compliance_case_document_linked,
     *                          compliance_case_linked_to_transaction,
     *                          compliance_case_linked_to_customer,
     *                          compliance_case_priority_changed)
     * @param  int  $caseId  Case ID
     * @param  array  $data  Case data
     */
    public function logComplianceCaseEvent(string $action, int $caseId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'compliance_case_priority_changed' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'ComplianceCase',
                'entity_id' => $caseId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log EDD template events.
     *
     * @param  string  $action  Template action (edd_template_created,
     *                          edd_template_updated, edd_template_deleted,
     *                          edd_template_duplicated)
     * @param  int  $templateId  Template ID
     * @param  array  $data  Template data
     */
    public function logEddTemplateEvent(string $action, int $templateId, array $data = []): SystemLog
    {
        $severity = $action === 'edd_template_deleted' ? 'WARNING' : 'INFO';

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'EddTemplate',
                'entity_id' => $templateId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log regulatory report events.
     *
     * @param  string  $action  Report action (regulatory_report_msb2_generated,
     *                          regulatory_report_lctr_generated,
     *                          regulatory_report_lmca_generated,
     *                          regulatory_report_qlvr_generated,
     *                          regulatory_report_position_limit_generated,
     *                          regulatory_report_submitted,
     *                          regulatory_report_acknowledged)
     * @param  int  $reportId  Report ID
     * @param  array  $data  Report data
     */
    public function logRegulatoryReportEvent(string $action, int $reportId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'regulatory_report_submitted' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'ReportGenerated',
                'entity_id' => $reportId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log data breach events.
     *
     * @param  string  $action  Breach action (data_breach_detected,
     *                          data_breach_acknowledged, data_breach_investigation_started,
     *                          data_breach_resolved, data_breach_false_positive)
     * @param  int  $breachId  Data breach alert ID
     * @param  array  $data  Breach data
     */
    public function logDataBreachEvent(string $action, int $breachId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'data_breach_detected', 'data_breach_acknowledged' => 'CRITICAL',
            'data_breach_investigation_started' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'DataBreachAlert',
                'entity_id' => $breachId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log session events.
     *
     * @param  string  $action  Session action (session_timeout,
     *                          session_extended, session_concurrent_blocked)
     * @param  array  $data  Session data
     */
    public function logSessionEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'session_concurrent_blocked' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $data['user_id'] ?? auth()->id(),
                'entity_type' => 'Session',
                'entity_id' => $data['session_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log permission denied events.
     *
     * @param  string  $resource  Resource being accessed
     * @param  string  $action  Action attempted
     * @param  string  $reason  Reason for denial
     * @param  array  $data  Additional context
     */
    public function logPermissionDenied(string $resource, string $action, string $reason, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            'permission_denied',
            [
                'user_id' => auth()->id(),
                'entity_type' => $resource,
                'entity_id' => $data['entity_id'] ?? null,
                'new_values' => [
                    'action' => $action,
                    'reason' => $reason,
                    'resource' => $resource,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ],
            'WARNING'
        );
    }

    /**
     * Log customer risk events.
     *
     * @param  string  $action  Risk action (customer_risk_score_changed,
     *                          customer_risk_level_upgraded,
     *                          customer_risk_level_downgraded,
     *                          customer_risk_locked, customer_risk_unlocked)
     * @param  int  $customerId  Customer ID
     * @param  array  $data  Risk data
     */
    public function logCustomerRiskEvent(string $action, int $customerId, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'customer_risk_level_upgraded', 'customer_risk_locked' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'Customer',
                'entity_id' => $customerId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log AML monitoring events.
     *
     * @param  string  $action  Monitor action (aml_velocity_alert_triggered,
     *                          aml_structuring_detected,
     *                          aml_sanctions_rescreen_completed, aml_rule_triggered)
     * @param  int|null  $entityId  Entity ID (transaction, customer, etc.)
     * @param  array  $data  Monitor data
     */
    public function logAmlMonitorEvent(string $action, ?int $entityId = null, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'aml_velocity_alert_triggered', 'aml_structuring_detected',
            'aml_rule_triggered' => 'ERROR',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => $data['entity_type'] ?? 'AmlMonitor',
                'entity_id' => $entityId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log sanctions screening events.
     *
     * @param  string  $action  Sanction action (sanction_screening_hit,
     *                          sanction_screening_passed, sanction_manual_override,
     *                          sanction_block_overridden)
     * @param  int|null  $entityId  Entity ID (customer, transaction)
     * @param  array  $data  Sanction data with old/new values
     */
    public function logSanctionEvent(string $action, ?int $entityId = null, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'sanction_screening_hit' => 'ERROR',
            'sanction_manual_override' => 'WARNING',
            'sanction_block_overridden' => 'CRITICAL',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => $data['entity_type'] ?? 'Sanction',
                'entity_id' => $entityId,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log currency position events.
     *
     * @param  string  $action  Position action (position_revaluation_run,
     *                          position_limit_breach, position_manual_adjustment)
     * @param  array  $data  Position data with old/new values
     */
    public function logPositionEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'position_limit_breach', 'position_manual_adjustment' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'entity_type' => 'CurrencyPosition',
                'entity_id' => $data['position_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log report access events.
     *
     * @param  string  $action  Access action (report_customer_history_viewed,
     *                          report_ctos_exported, report_audit_log_viewed,
     *                          report_data_export)
     * @param  array  $data  Report access data with old/new values
     */
    public function logReportAccessEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'report_ctos_exported', 'report_audit_log_viewed',
            'report_data_export' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => auth()->id(),
                'entity_type' => $data['entity_type'] ?? 'Report',
                'entity_id' => $data['entity_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log API access events.
     *
     * @param  string  $action  API action (api_login_success, api_login_failed,
     *                          api_str_submitted, api_bulk_import)
     * @param  array  $data  API access data
     */
    public function logApiAccessEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'api_login_failed' => 'WARNING',
            'api_str_submitted' => 'CRITICAL',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $data['user_id'] ?? auth()->id(),
                'entity_type' => 'ApiAccess',
                'entity_id' => $data['entity_id'] ?? null,
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log cross-branch access events.
     *
     * @param  int  $accessedBranchId  Branch ID being accessed
     * @param  string  $resource  Resource type accessed
     * @param  int  $resourceId  Resource ID accessed
     * @param  array  $data  Access data
     */
    public function logBranchAccessEvent(int $accessedBranchId, string $resource, int $resourceId, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            'cross_branch_access',
            [
                'user_id' => auth()->id(),
                'entity_type' => $resource,
                'entity_id' => $resourceId,
                'new_values' => [
                    'accessed_branch_id' => $accessedBranchId,
                    'accessed_branch_name' => $data['branch_name'] ?? null,
                    'user_branch_id' => auth()->user()?->branch_id ?? null,
                ],
            ],
            'WARNING'
        );
    }

    /**
     * Log batch operation events.
     *
     * @param  string  $action  Batch action (batch_import_completed,
     *                          batch_approval_completed)
     * @param  array  $data  Batch operation data
     */
    public function logBatchOperationEvent(string $action, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            $action,
            [
                'user_id' => auth()->id(),
                'entity_type' => 'BatchOperation',
                'entity_id' => $data['batch_id'] ?? null,
                'new_values' => [
                    'items_processed' => $data['items_processed'] ?? 0,
                    'items_succeeded' => $data['items_succeeded'] ?? 0,
                    'items_failed' => $data['items_failed'] ?? 0,
                ],
            ],
            'INFO'
        );
    }

    /**
     * Verify the integrity of the audit log chain.
     *
     * Checks that each entry's stored hash matches the recomputed hash
     * based on its actual data and the previous entry's hash.
     *
     * @param  int|null  $limit  Number of recent entries to verify (null = all)
     * @return array{valid: bool, broken_at: int|null, message: string}
     */
    public function verifyChainIntegrity(?int $limit = null): array
    {
        $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

        if ($limit !== null) {
            // Get the last N entries and verify backwards
            $totalCount = SystemLog::count();
            $query = SystemLog::whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->orderBy('id', 'asc');
        }

        $entries = $query->get();
        $previousHash = null;

        foreach ($entries as $entry) {
            // Skip entries without hash (migrated data)
            if (empty($entry->entry_hash)) {
                continue;
            }

            // Verify the previous_hash chain link using timing-safe comparison
            if (! hash_equals((string) $previousHash, (string) $entry->previous_hash)) {
                return [
                    'valid' => false,
                    'broken_at' => $entry->id,
                    'message' => "Chain broken at entry {$entry->id}: previous_hash mismatch. Expected ".
                        ($previousHash ?? 'null').', got '.($entry->previous_hash ?? 'null'),
                ];
            }

            // Recompute the entry hash and verify it matches
            $recomputedHash = $this->computeEntryHash(
                $entry->created_at->toIso8601String(),
                $entry->user_id,
                $entry->action,
                $entry->entity_type,
                $entry->entity_id,
                $entry->previous_hash
            );

            if (! hash_equals($recomputedHash, (string) $entry->entry_hash)) {
                return [
                    'valid' => false,
                    'broken_at' => $entry->id,
                    'message' => "Hash mismatch at entry {$entry->id}: stored hash appears to have been tampered with.",
                ];
            }

            $previousHash = $entry->entry_hash;
        }

        return [
            'valid' => true,
            'broken_at' => null,
            'message' => "Chain integrity verified: {$entries->count()} entries checked.",
        ];
    }

    /**
     * Get count of unsealed audit log entries.
     * Useful for monitoring/alerting on the async hash sealing pipeline.
     */
    public function getUnsealedCount(): int
    {
        return SystemLog::whereNull('entry_hash')->count();
    }
}
