<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsReporting
{
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
     * Log report access events.
     *
     * @param  string  $action  Access action (report_customer_history_viewed,
     *                          report_audit_log_viewed, report_data_export)
     * @param  array  $data  Report access data with old/new values
     */
    public function logReportAccessEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'report_audit_log_viewed',
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
}
