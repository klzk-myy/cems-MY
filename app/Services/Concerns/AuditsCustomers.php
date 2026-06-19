<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsCustomers
{
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
}
