<?php

namespace App\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\RiskRating;
use App\Enums\UserRole;
use App\Events\AlertCreated;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertTriageService
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
        protected CaseManagementService $caseManagementService,
        protected AuditService $auditService,
    ) {}

    /**
     * Create an alert from a flagged transaction.
     */
    public function createFromFlaggedTransaction(FlaggedTransaction $flaggedTransaction): Alert
    {
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
     */
    public function calculateRiskScore(
        FlaggedTransaction $flaggedTransaction,
        ?Customer $customer = null,
        ?Transaction $transaction = null
    ): int {
        $score = 0;

        $customer = $customer ?? $flaggedTransaction->customer;
        $transaction = $transaction ?? $flaggedTransaction->transaction;

        if ($transaction) {
            $amount = (string) $transaction->amount_local;
            $criticalThreshold = $this->thresholdService->getAlertCriticalThreshold();
            $highThreshold = $this->thresholdService->getAlertHighThreshold();
            $mediumThreshold = $this->thresholdService->getAlertMediumThreshold();

            if ($this->mathService->compare($amount, $criticalThreshold) >= 0) {
                $score += 30;
            } elseif ($this->mathService->compare($amount, $highThreshold) >= 0) {
                $score += 20;
            } elseif ($this->mathService->compare($amount, $mediumThreshold) >= 0) {
                $score += 10;
            }
        }

        if ($customer) {
            $riskRating = $customer->risk_rating;
            if ($riskRating instanceof RiskRating) {
                if ($riskRating === RiskRating::High || $riskRating === RiskRating::Medium) {
                    $score += $riskRating === RiskRating::High ? 20 : 10;
                }
            } elseif (is_string($riskRating)) {
                if (in_array($riskRating, ['high', 'critical'])) {
                    $score += 20;
                } elseif ($riskRating === 'medium') {
                    $score += 10;
                }
            }

            if ($customer->pep_status) {
                $score += 10;
            }

            if ($customer->sanction_hit) {
                $score += 30;
            }
        }

        $flagType = $flaggedTransaction->flag_type;
        if ($flagType === ComplianceFlagType::Velocity) {
            $score += 5;
        } elseif ($flagType === ComplianceFlagType::Structuring) {
            $score += 10;
        }

        $recentAlerts = Alert::where('customer_id', $flaggedTransaction->customer_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($recentAlerts >= 3) {
            $score += 15;
        } elseif ($recentAlerts >= 1) {
            $score += 5;
        }

        if ($flaggedTransaction->flag_type === ComplianceFlagType::HighRiskCountry) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * Get unassigned alerts ordered by priority.
     */
    public function getUnassignedAlerts(): Collection
    {
        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::with(['customer', 'flaggedTransaction'])
            ->whereNull('case_id')
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
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

        $alert->update(['assigned_to' => $userId]);

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

        $workloads = $officers->mapWithKeys(fn ($o) => [$o->id => 0]);

        // Min-heap keyed by workload so selecting the least-loaded officer is
        // O(1) per alert instead of sorting the full map on every iteration.
        $queue = new \SplPriorityQueue;
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        foreach ($workloads as $officerId => $count) {
            $queue->insert($officerId, -$count);
        }

        foreach ($unassignedAlerts as $alert) {
            $minWorkloadOfficer = $queue->extract();

            $this->assignToOfficer($alert, $minWorkloadOfficer);
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
            $alert->update([
                'status' => FlagStatus::Resolved,
                'case_id' => null,
            ]);

            if ($alert->flaggedTransaction) {
                $alert->flaggedTransaction->update([
                    'status' => FlagStatus::Resolved,
                    'reviewed_by' => $resolvedBy,
                    'resolved_at' => now(),
                    'notes' => $notes,
                ]);
            }

            return $alert->fresh();
        });
    }

    public function dismissAlert(Alert $alert, int $dismissedBy): Alert
    {
        if ($alert->status === FlagStatus::Resolved || $alert->status === FlagStatus::Rejected) {
            throw new CaseManagementException('Cannot dismiss an already resolved or rejected alert.');
        }

        return DB::transaction(function () use ($alert, $dismissedBy) {
            $alert->update([
                'status' => FlagStatus::Rejected,
                'reviewed_by' => $dismissedBy,
            ]);

            if ($alert->flaggedTransaction) {
                $alert->flaggedTransaction->update([
                    'status' => FlagStatus::Rejected,
                    'reviewed_by' => $dismissedBy,
                    'resolved_at' => now(),
                ]);
            }

            return $alert->fresh();
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
     * Get alert queue summary.
     */
    public function getQueueSummary(): array
    {
        $baseQuery = Alert::whereNull('case_id');

        return [
            'total' => $baseQuery->count(),
            'critical' => $baseQuery->where('priority', AlertPriority::Critical)->count(),
            'high' => $baseQuery->where('priority', AlertPriority::High)->count(),
            'medium' => $baseQuery->where('priority', AlertPriority::Medium)->count(),
            'low' => $baseQuery->where('priority', AlertPriority::Low)->count(),
            'unassigned' => $baseQuery->whereNull('assigned_to')->count(),
            'overdue' => $this->getOverdueCount(),
            'pending' => $baseQuery->where('status', FlagStatus::Open)->count(),
            'in_progress' => $baseQuery->whereIn('status', [FlagStatus::UnderReview, FlagStatus::Escalated])->count(),
            'resolved_today' => Alert::whereDate('updated_at', today())
                ->where('status', FlagStatus::Resolved)->count(),
        ];
    }

    /**
     * Get count of overdue alerts.
     * Uses database-level filtering for efficiency.
     */
    protected function getOverdueCount(): int
    {
        // Compute overdue in database based on SLA hours per priority
        return Alert::query()
            ->whereNull('case_id')
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Critical: 4 hours
                    $q->where('priority', AlertPriority::Critical->value)
                        ->where('created_at', '<', now()->subHours(4));
                })->orWhere(function ($q) {
                    // High: 8 hours
                    $q->where('priority', AlertPriority::High->value)
                        ->where('created_at', '<', now()->subHours(8));
                })->orWhere(function ($q) {
                    // Medium: 24 hours
                    $q->where('priority', AlertPriority::Medium->value)
                        ->where('created_at', '<', now()->subHours(24));
                })->orWhere(function ($q) {
                    // Low: 72 hours
                    $q->where('priority', AlertPriority::Low->value)
                        ->where('created_at', '<', now()->subHours(72));
                });
            })
            ->count();
    }

    /**
     * Bulk assign alerts to a compliance officer.
     *
     * @param  array  $alertIds  Array of alert IDs
     * @param  int  $userId  User ID to assign to
     * @return array Results with success and failure counts
     */
    public function bulkAssign(array $alertIds, int $userId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
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
     * @param  array  $alertIds  Array of alert IDs
     * @param  int  $resolvedBy  User ID who is resolving
     * @param  string|null  $notes  Optional notes for all resolved alerts
     * @return array Results with success and failure counts
     */
    public function bulkResolve(array $alertIds, int $resolvedBy, ?string $notes = null): array
    {
        return DB::transaction(function () use ($alertIds, $resolvedBy, $notes) {
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];
            $alerts = Alert::whereIn('id', $alertIds)->get()->keyBy('id');

            foreach ($alertIds as $alertId) {
                try {
                    $alert = $alerts->get($alertId);
                    if (! $alert) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} not found";

                        continue;
                    }

                    if ($alert->status === FlagStatus::Resolved) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} is already resolved";

                        continue;
                    }

                    $this->resolveAlert($alert, $resolvedBy, $notes);
                    $results['success']++;
                } catch (\Exception $e) {
                    Log::error('Alert bulk-resolve failed', ['alert_id' => $alertId, 'error' => $e->getMessage()]);
                    $results['failed']++;
                    $results['errors'][] = "Alert {$alertId}: {$e->getMessage()}";
                }
            }

            return $results;
        });
    }

    /**
     * Bulk link alerts to a case.
     *
     * @param  array  $alertIds  Array of alert IDs
     * @param  ComplianceCase  $case  The case to link alerts to
     * @return array Results with success and failure counts
     */
    public function bulkLinkToCase(array $alertIds, ComplianceCase $case): array
    {
        return DB::transaction(function () use ($alertIds, $case) {
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];
            $alerts = Alert::whereIn('id', $alertIds)->get()->keyBy('id');

            foreach ($alertIds as $alertId) {
                try {
                    $alert = $alerts->get($alertId);
                    if (! $alert) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} not found";

                        continue;
                    }

                    // Delegate to the single-link workflow so the exact same
                    // guards apply (reject alerts already linked to another
                    // case or belonging to a different customer than the
                    // case) and case priority/SLA are recalculated after.
                    $this->caseManagementService->linkAlertToCase($alert, $case);
                    $results['success']++;
                } catch (\Exception $e) {
                    Log::error('Alert bulk-link failed', ['alert_id' => $alertId, 'error' => $e->getMessage()]);
                    $results['failed']++;
                    $results['errors'][] = "Alert {$alertId}: {$e->getMessage()}";
                }
            }

            return $results;
        });
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

    /**
     * Escalate an alert to a higher severity level.
     */
    public function escalateAlert(Alert $alert, int $escalatedBy, string $reason): Alert
    {
        return DB::transaction(function () use ($alert, $escalatedBy, $reason) {
            $currentPriority = $alert->priority;

            // Bump priority up one level
            $newPriority = match ($currentPriority) {
                AlertPriority::Low => AlertPriority::Medium,
                AlertPriority::Medium => AlertPriority::High,
                AlertPriority::High => AlertPriority::Critical,
                default => AlertPriority::Critical,
            };

            $alert->update([
                'priority' => $newPriority,
                'escalated_at' => now(),
                'escalation_reason' => $reason,
            ]);

            $this->auditService->logWithSeverity(
                'alert_escalated',
                [
                    'description' => "Alert #{$alert->id} escalated from {$currentPriority->value} to {$newPriority->value}: {$reason}",
                    'alert_id' => $alert->id,
                    'from_priority' => $currentPriority->value,
                    'to_priority' => $newPriority->value,
                    'reason' => $reason,
                    'escalated_by' => $escalatedBy,
                ],
                'warning'
            );

            return $alert;
        });
    }
}
