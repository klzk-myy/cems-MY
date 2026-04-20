<?php

namespace App\Services;

use App\Enums\AlertPriority;
use App\Enums\FlagStatus;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Collection;

/**
 * Compliance Alert Service
 *
 * Handles all compliance alert-related business logic including:
 * - Alert status checks
 * - SLA deadline calculations
 * - Overdue detection
 * - Alert assignment and triage
 *
 * This service removes business logic from the Alert model,
 * ensuring proper MVC separation of concerns.
 */
class ComplianceAlertService
{
    /**
     * Calculate SLA deadline for an alert.
     *
     * @param  Alert  $alert  Alert to calculate deadline for
     * @return \DateTime SLA deadline
     */
    public function calculateSlaDeadline(Alert $alert): \DateTime
    {
        return now()->addHours($alert->priority->slaHours());
    }

    /**
     * Determine if an alert is overdue.
     *
     * An alert is overdue if:
     * - It has no associated case (not yet resolved)
     * - Current time is after SLA deadline
     *
     * @param  Alert  $alert  Alert to check
     * @return bool True if alert is overdue
     */
    public function isOverdue(Alert $alert): bool
    {
        if ($alert->case_id) {
            return false;
        }

        return now()->isAfter($this->calculateSlaDeadline($alert));
    }

    /**
     * Determine if an alert is resolved.
     *
     * @param  Alert  $alert  Alert to check
     * @return bool True if alert is resolved
     */
    public function isResolved(Alert $alert): bool
    {
        return $alert->status === FlagStatus::Resolved;
    }

    /**
     * Assign an alert to a user.
     *
     * @param  Alert  $alert  Alert to assign
     * @param  int  $userId  User ID to assign to
     * @return Alert Updated alert
     */
    public function assignAlert(Alert $alert, int $userId): Alert
    {
        $alert->update([
            'assigned_to' => $userId,
            'status' => FlagStatus::UnderReview,
        ]);

        return $alert->fresh();
    }

    /**
     * Resolve an alert.
     *
     * @param  Alert  $alert  Alert to resolve
     * @param  int  $userId  User ID resolving the alert
     * @param  string|null  $notes  Resolution notes
     * @return Alert Updated alert
     */
    public function resolveAlert(Alert $alert, int $userId, ?string $notes = null): Alert
    {
        $alert->update([
            'status' => FlagStatus::Resolved,
            'reviewed_by' => $userId,
            'notes' => $notes,
            'resolved_at' => now(),
        ]);

        return $alert->fresh();
    }

    /**
     * Get overdue alerts.
     *
     * @param  int  $hours  Hours threshold for overdue
     * @return Collection Collection of overdue alerts
     */
    public function getOverdueAlerts(int $hours = 24): Collection
    {
        return Alert::whereNull('case_id')
            ->where('created_at', '<', now()->subHours($hours))
            ->where('status', '!=', FlagStatus::Resolved)
            ->get();
    }

    /**
     * Get unassigned alerts.
     *
     * @return Collection Collection of unassigned alerts
     */
    public function getUnassignedAlerts(): Collection
    {
        return Alert::whereNull('assigned_to')
            ->where('status', '!=', FlagStatus::Resolved)
            ->get();
    }

    /**
     * Get alerts by priority.
     *
     * @param  AlertPriority  $priority  Priority level
     * @return Collection Collection of alerts
     */
    public function getAlertsByPriority(AlertPriority $priority): Collection
    {
        return Alert::where('priority', $priority)
            ->where('status', '!=', FlagStatus::Resolved)
            ->get();
    }

    /**
     * Get open alerts (not associated with a case).
     *
     * @return Collection Collection of open alerts
     */
    public function getOpenAlerts(): Collection
    {
        return Alert::whereNull('case_id')
            ->where('status', '!=', FlagStatus::Resolved)
            ->get();
    }

    /**
     * Get resolved alerts.
     *
     * @return Collection Collection of resolved alerts
     */
    public function getResolvedAlerts(): Collection
    {
        return Alert::where('status', FlagStatus::Resolved)
            ->get();
    }
}
