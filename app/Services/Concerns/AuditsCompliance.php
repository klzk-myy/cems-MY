<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsCompliance
{
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
}
