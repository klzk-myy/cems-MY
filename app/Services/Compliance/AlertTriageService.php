<?php

namespace App\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\Permission;
use App\Enums\RiskRating;
use App\Enums\UserRole;
use App\Events\AlertCreated;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Compliance\Alert;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertTriageService
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
        protected AuditService $auditService,
        protected RiskScoringEngine $riskScoringEngine,
    ) {}

    /**
     * Create an alert from a flagged transaction.
     */
    public function createFromFlaggedTransaction(FlaggedTransaction $flaggedTransaction): Alert
    {
        // Batched by the caller (TransactionMonitoringService); loadMissing is
        // a no-op there but keeps this method self-sufficient if invoked alone.
        $flaggedTransaction->loadMissing(['customer', 'transaction']);

        $customer = $flaggedTransaction->customer;
        $transaction = $flaggedTransaction->transaction;

        $riskScore = $this->calculateRiskScore(
            $flaggedTransaction,
            $customer instanceof Customer ? $customer : null,
            $transaction instanceof Transaction ? $transaction : null
        );
        $priority = AlertPriority::fromRiskScore($riskScore);

        $alert = Alert::create([
            'flagged_transaction_id' => $flaggedTransaction->id,
            'customer_id' => $flaggedTransaction->customer_id,
            'type' => $flaggedTransaction->flag_type,
            'priority' => $priority,
            'risk_score' => $riskScore,
            'reason' => $flaggedTransaction->flag_reason,
            'source' => 'System',
            'case_id' => null,
        ]);

        event(new AlertCreated($alert));

        return $alert;
    }

    /**
     * Calculate risk score for an alert.
     *
     * The score is anchored on the customer's multi-factor score from the
     * canonical RiskScoringEngine (geographic risk, PEP status, transaction
     * deviation, velocity, structuring, EDD history) so every score in the
     * system traces to one computation — previously alert.risk_score and
     * customer.risk_score were two unrelated numbers for the same customer.
     * Transaction amount, customer attributes, flag type and repeat-offender
     * history layer on top as deltas, clamped to 100.
     */
    public function calculateRiskScore(
        FlaggedTransaction $flaggedTransaction,
        ?Customer $customer = null,
        ?Transaction $transaction = null
    ): int {
        $customer = $customer ?? $flaggedTransaction->customer;
        $transaction = $transaction ?? $flaggedTransaction->transaction;

        $baseScore = $customer !== null
            ? $this->riskScoringEngine->calculateScore($customer->id)
            : 0;

        // Amount delta: the specific transaction size that triggered the
        // flag, layered on top of the customer base.
        $amountDelta = 0;
        if ($transaction) {
            $amountMyr = (string) $transaction->amount_myr;
            $criticalThreshold = $this->thresholdService->getAlertCriticalThreshold();
            $highThreshold = $this->thresholdService->getAlertHighThreshold();
            $mediumThreshold = $this->thresholdService->getAlertMediumThreshold();

            if ($this->mathService->compare($amountMyr, $criticalThreshold) >= 0) {
                $amountDelta = 30;
            } elseif ($this->mathService->compare($amountMyr, $highThreshold) >= 0) {
                $amountDelta = 20;
            } elseif ($this->mathService->compare($amountMyr, $mediumThreshold) >= 0) {
                $amountDelta = 10;
            }
        }

        // Customer attribute deltas: risk rating, PEP status, sanctions hit.
        $attributeDelta = 0;
        if ($customer) {
            $riskRating = $customer->risk_rating;
            if ($riskRating instanceof RiskRating) {
                if ($riskRating === RiskRating::High || $riskRating === RiskRating::Medium) {
                    $attributeDelta += $riskRating === RiskRating::High ? 20 : 10;
                }
            } elseif (is_string($riskRating)) {
                if (in_array($riskRating, ['high', 'critical'])) {
                    $attributeDelta += 20;
                } elseif ($riskRating === 'medium') {
                    $attributeDelta += 10;
                }
            }

            if ($customer->pep_status) {
                $attributeDelta += 10;
            }

            if ($customer->sanction_hit) {
                $attributeDelta += 30;
            }
        }

        // Flag-type delta: the nature of the flag itself.
        $flagType = $flaggedTransaction->flag_type;
        $flagDelta = match ($flagType) {
            ComplianceFlagType::Velocity => 5,
            ComplianceFlagType::Structuring => 10,
            ComplianceFlagType::HighRiskCountry => 10,
            default => 0,
        };

        // Repeat-offender delta: three or more alerts in the past week means
        // the customer's behaviour is not improving.
        $recentAlerts = Alert::where('customer_id', $flaggedTransaction->customer_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $repeatDelta = match (true) {
            $recentAlerts >= 3 => 15,
            $recentAlerts >= 1 => 5,
            default => 0,
        };

        return min($baseScore + $amountDelta + $attributeDelta + $flagDelta + $repeatDelta, 100);
    }

    /**
     * Get unassigned alerts ordered by priority.
     */
    public function getUnassignedAlerts(): Collection
    {
        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::with(['customer', 'flaggedTransaction'])
            ->whereNull('case_id')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('risk_score')
            ->get();

        return $alerts;
    }

    /**
     * Assign alert to a compliance officer.
     */
    public function assignToOfficer(Alert $alert, int $userId): Alert
    {
        if ($alert->case_id !== null) {
            throw new CaseManagementException('Alert is linked to a case; use case assignment workflow instead');
        }

        $assignee = User::find($userId);

        if (! $assignee?->is_active || ! in_array($assignee->role, [UserRole::ComplianceOfficer, UserRole::Manager], true)) {
            throw new CaseManagementException("User {$userId} cannot be assigned alerts: assignee must be an active compliance officer or manager");
        }

        $previousAssignee = $alert->assigned_to;

        // Assignment and its audit entry commit together — the same atomicity
        // resolveAlert/dismissAlert already get from their DB::transaction
        // wrappers, and the guarantee bulkAssign relies on per item.
        DB::transaction(function () use ($alert, $userId, $previousAssignee) {
            $alert->update(['assigned_to' => $userId]);

            $this->auditService->logWithSeverity(
                'alert_assigned',
                [
                    'description' => "Alert #{$alert->id} assigned to user {$userId}".($previousAssignee ? " (reassigned from {$previousAssignee})" : ''),
                    'alert_id' => $alert->id,
                    'assigned_to' => $userId,
                    'previous_assignee' => $previousAssignee,
                ],
                'INFO'
            );
        });

        return $alert->fresh();
    }

    /**
     * Auto-assign alerts based on workload balance.
     */
    public function autoAssignAlerts(): array
    {
        $unassignedAlerts = $this->getUnassignedAlerts();
        $assigned = [];

        $officers = $this->getAvailableOfficers();

        if ($officers->isEmpty()) {
            return $assigned;
        }

        // Plain array, not a Collection: workloads are incremented in place
        // below, and indirect modification ($collection[$id]++) is a no-op.
        $workloads = array_fill_keys($officers->pluck('id')->all(), 0);

        // Min-heap keyed by workload so selecting the least-loaded officer is
        // O(1) per alert instead of sorting the full map on every iteration.
        $queue = new \SplPriorityQueue;
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        foreach ($workloads as $officerId => $count) {
            $queue->insert($officerId, -$count);
        }

        foreach ($unassignedAlerts as $alert) {
            $minWorkloadOfficer = $queue->extract();

            try {
                $this->assignToOfficer($alert, $minWorkloadOfficer);
            } catch (\Exception $e) {
                Log::error('Alert auto-assign failed', ['alert_id' => $alert->getKey(), 'error' => $e->getMessage()]);
                $queue->insert($minWorkloadOfficer, -$workloads[$minWorkloadOfficer]);

                continue;
            }

            $workloads[$minWorkloadOfficer]++;
            // Re-insert with the incremented workload (negative priority = min-heap).
            $queue->insert($minWorkloadOfficer, -$workloads[$minWorkloadOfficer]);
            $assigned[] = $alert;
        }

        return $assigned;
    }

    /**
     * Resolve an alert.
     */
    public function resolveAlert(Alert $alert, int $resolvedBy, ?string $notes = null): Alert
    {
        return DB::transaction(function () use ($alert, $resolvedBy, $notes) {
            // Re-read under a row lock so the state guard and the mutation are atomic;
            // the stale in-memory status check before the transaction was a TOCTOU race.
            $lockedAlert = Alert::whereKey($alert->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAlert->status->isTerminal()) {
                throw new CaseManagementException('Cannot resolve an already resolved or rejected alert.');
            }

            $lockedAlert->update([
                'status' => AlertStatus::Resolved,
                'case_id' => null,
                'reviewed_by' => $resolvedBy,
                'resolved_at' => now(),
            ]);

            if ($lockedAlert->flaggedTransaction) {
                $lockedAlert->flaggedTransaction->update([
                    'status' => FlagStatus::Resolved,
                    'reviewed_by' => $resolvedBy,
                    'resolved_at' => now(),
                    'notes' => $notes,
                ]);
            }

            $this->auditService->logWithSeverity(
                'alert_resolved',
                [
                    'description' => "Alert #{$lockedAlert->id} resolved",
                    'alert_id' => $lockedAlert->id,
                    'resolved_by' => $resolvedBy,
                    'notes' => $notes,
                ],
                'INFO'
            );

            return $lockedAlert->fresh();
        });
    }

    public function dismissAlert(Alert $alert, int $dismissedBy): Alert
    {
        return DB::transaction(function () use ($alert, $dismissedBy) {
            $lockedAlert = Alert::whereKey($alert->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAlert->status->isTerminal()) {
                throw new CaseManagementException('Cannot dismiss an already resolved or rejected alert.');
            }

            $lockedAlert->update([
                'status' => AlertStatus::Rejected,
                'reviewed_by' => $dismissedBy,
                'resolved_at' => now(),
            ]);

            if ($lockedAlert->flaggedTransaction) {
                $lockedAlert->flaggedTransaction->update([
                    'status' => FlagStatus::Rejected,
                    'reviewed_by' => $dismissedBy,
                    'resolved_at' => now(),
                ]);
            }

            $this->auditService->logWithSeverity(
                'alert_dismissed',
                [
                    'description' => "Alert #{$lockedAlert->id} dismissed",
                    'alert_id' => $lockedAlert->id,
                    'dismissed_by' => $dismissedBy,
                ],
                'INFO'
            );

            return $lockedAlert->fresh();
        });
    }

    /**
     * Get available compliance officers.
     *
     * @return Collection<int, User>
     */
    public function getAvailableOfficers(): Collection
    {
        return User::whereIn('role', [UserRole::ComplianceOfficer->value, UserRole::Manager->value])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Notify all available compliance officers, optionally excluding one user
     * (typically the actor who triggered the escalation).
     *
     * Per-officer failures are logged and swallowed so a single broken
     * notification channel can never block the remaining officers.
     *
     * @return int Number of officers the notification was dispatched to
     */
    public function notifyAvailableOfficers(Notification $notification, ?int $excludeUserId = null): int
    {
        $officers = $this->getAvailableOfficers()
            ->when(
                $excludeUserId !== null,
                fn (Collection $c) => $c->reject(fn (User $officer) => $officer->id === $excludeUserId)
            )
            ->values();

        foreach ($officers as $officer) {
            try {
                $officer->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify compliance officer', [
                    'officer_id' => $officer->id,
                    'notification' => $notification::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $officers->count();
    }

    /**
     * Get alert queue summary.
     */
    public function getQueueSummary(): array
    {
        $baseQuery = Alert::whereNull('case_id');

        return [
            'total' => $baseQuery->count(),
            'critical' => $baseQuery->where('priority', AlertPriority::Critical->value)->count(),
            'high' => $baseQuery->where('priority', AlertPriority::High->value)->count(),
            'medium' => $baseQuery->where('priority', AlertPriority::Medium->value)->count(),
            'low' => $baseQuery->where('priority', AlertPriority::Low->value)->count(),
            'unassigned' => $baseQuery->whereNull('assigned_to')->count(),
            'overdue' => $this->getOverdueCount(),
            'pending' => $baseQuery->where('status', AlertStatus::Open->value)->count(),
            'in_progress' => $baseQuery->whereIn('status', [AlertStatus::UnderReview->value, AlertStatus::Escalated->value])->count(),
            'resolved_today' => Alert::whereBetween('updated_at', [today()->startOfDay(), today()->endOfDay()])
                ->where('status', AlertStatus::Resolved->value)->count(),
        ];
    }

    /**
     * Get count of overdue alerts.
     * Uses database-level filtering for efficiency.
     */
    protected function getOverdueCount(): int
    {
        // SLA hours per priority via ThresholdService so persisted DB
        // overrides apply (thresholds.alert_sla_hours.* leaves).
        $sla = [
            'critical' => (int) $this->thresholdService->get('alert_sla_hours', 'critical', 4),
            'high' => (int) $this->thresholdService->get('alert_sla_hours', 'high', 8),
            'medium' => (int) $this->thresholdService->get('alert_sla_hours', 'medium', 24),
            'low' => (int) $this->thresholdService->get('alert_sla_hours', 'low', 72),
        ];

        // Compute overdue in database based on SLA hours per priority
        return Alert::query()
            ->whereNull('case_id')
            ->where(function ($query) use ($sla) {
                $query->where(function ($q) use ($sla) {
                    $q->where('priority', AlertPriority::Critical->value)
                        ->where('created_at', '<', now()->subHours($sla['critical']));
                })->orWhere(function ($q) use ($sla) {
                    $q->where('priority', AlertPriority::High->value)
                        ->where('created_at', '<', now()->subHours($sla['high']));
                })->orWhere(function ($q) use ($sla) {
                    $q->where('priority', AlertPriority::Medium->value)
                        ->where('created_at', '<', now()->subHours($sla['medium']));
                })->orWhere(function ($q) use ($sla) {
                    $q->where('priority', AlertPriority::Low->value)
                        ->where('created_at', '<', now()->subHours($sla['low']));
                });
            })
            ->count();
    }

    /**
     * Bulk assign alerts to a compliance officer.
     *
     * @param  array<int, int>  $alertIds  Array of alert IDs
     * @param  int  $userId  User ID to assign to
     * @return array{success: int, failed: int, errors: array<int, string>}
     */
    public function bulkAssign(array $alertIds, int $userId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        // Re-verify the assignee still holds the compliance permission: the
        // request only validates the user exists, so without this check a
        // demoted user could still be assigned cases via this endpoint.
        $assignee = User::find($userId);
        if (! $assignee instanceof User || ! $assignee->role->canPerform(Permission::AccessCompliance)) {
            foreach ($alertIds as $alertId) {
                $results['failed']++;
                $results['errors'][] = "User {$userId} is not authorized to receive alert assignments";
            }

            return $results;
        }

        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::whereIn('id', $alertIds)->get()->keyBy('id');

        foreach ($alertIds as $alertId) {
            try {
                $alert = $alerts->get($alertId);
                if (! $alert) {
                    $results['failed']++;
                    $results['errors'][] = "Alert {$alertId} not found";

                    continue;
                }

                if ($alert->case_id !== null) {
                    $results['failed']++;
                    $results['errors'][] = "Alert {$alertId} is already linked to a case";

                    continue;
                }

                $this->assignToOfficer($alert, $userId);
                $results['success']++;
            } catch (\Exception $e) {
                Log::error('Alert bulk-assign failed', ['alert_id' => $alertId, 'error' => $e->getMessage()]);
                $results['failed']++;
                $results['errors'][] = "Alert {$alertId}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Bulk resolve multiple alerts.
     *
     * @param  array<int, int>  $alertIds  Array of alert IDs
     * @param  int  $resolvedBy  User ID who is resolving
     * @param  string|null  $notes  Optional notes for all resolved alerts
     * @return array{success: int, failed: int, errors: array<int, string>}
     */
    public function bulkResolve(array $alertIds, int $resolvedBy, ?string $notes = null): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        $alerts = Alert::whereIn('id', $alertIds)->get()->keyBy('id');

        // One transaction per alert: an item that fails rolls back only its
        // own mutations, so the reported success count always matches
        // committed state. A single outer transaction made earlier
        // successes hostages to a later failure at commit time.
        foreach ($alertIds as $alertId) {
            try {
                DB::transaction(function () use ($alertId, $alerts, $resolvedBy, $notes, &$results) {
                    $alert = $alerts->get($alertId);
                    if (! $alert) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} not found";

                        return;
                    }

                    if ($alert->status === AlertStatus::Resolved) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} is already resolved";

                        return;
                    }

                    $this->resolveAlert($alert, $resolvedBy, $notes);
                    $results['success']++;
                });
            } catch (\Exception $e) {
                Log::error('Alert bulk-resolve failed', ['alert_id' => $alertId, 'error' => $e->getMessage()]);
                $results['failed']++;
                $results['errors'][] = "Alert {$alertId}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Get alerts by IDs for bulk operations.
     */
    public function getByIds(array $alertIds): Collection
    {
        return Alert::with(['customer', 'flaggedTransaction', 'assignedTo'])
            ->whereIn('id', $alertIds)
            ->get();
    }
}
