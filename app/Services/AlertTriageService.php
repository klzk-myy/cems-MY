<?php

namespace App\Services;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Events\AlertCreated;
use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AlertTriageService
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected ThresholdService $thresholdService,
    ) {}

    /**
     * Create an alert from a flagged transaction.
     */
    public function createFromFlaggedTransaction(FlaggedTransaction $flaggedTransaction): Alert
    {
        $customer = $flaggedTransaction->customer;
        $transaction = $flaggedTransaction->transaction;

        $riskScore = $this->calculateRiskScore($flaggedTransaction, $customer, $transaction);
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

            if ($criticalThreshold !== null && bccomp($amount, $criticalThreshold, 4) >= 0) {
                $score += 30;
            } elseif ($highThreshold !== null && bccomp($amount, $highThreshold, 4) >= 0) {
                $score += 20;
            } elseif ($mediumThreshold !== null && bccomp($amount, $mediumThreshold, 4) >= 0) {
                $score += 10;
            }
        }

        if ($customer) {
            $riskRating = $customer->risk_rating ?? 'low';
            if (in_array($riskRating, ['high', 'critical'])) {
                $score += 20;
            } elseif ($riskRating === 'medium') {
                $score += 10;
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
        return Alert::with(['customer', 'flaggedTransaction'])
            ->whereNull('case_id')
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('risk_score')
            ->get();
    }

    /**
     * Assign alert to a compliance officer.
     */
    public function assignToOfficer(Alert $alert, int $userId): Alert
    {
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

        foreach ($unassignedAlerts as $alert) {
            $minWorkloadOfficer = $workloads->sort()->keys()->first();
            $this->assignToOfficer($alert, $minWorkloadOfficer);
            $workloads[$minWorkloadOfficer]++;
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

    /**
     * Get available compliance officers.
     */
    protected function getAvailableOfficers(): Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['compliance', 'manager']);
        })
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
        return DB::table('alerts')
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

        foreach ($alertIds as $alertId) {
            try {
                $alert = Alert::find($alertId);
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

            foreach ($alertIds as $alertId) {
                try {
                    $alert = Alert::find($alertId);
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

            foreach ($alertIds as $alertId) {
                try {
                    $alert = Alert::find($alertId);
                    if (! $alert) {
                        $results['failed']++;
                        $results['errors'][] = "Alert {$alertId} not found";

                        continue;
                    }

                    $alert->update(['case_id' => $case->id]);
                    $results['success']++;
                } catch (\Exception $e) {
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
}
