<?php

namespace App\Services\Compliance;

use App\Enums\FlagStatus;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use App\Services\System\CacheOptimizationService;
use Illuminate\Support\Facades\DB;

class ComplianceFlagService
{
    public function __construct(
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
        protected CacheOptimizationService $cacheOptimizationService,
    ) {}

    public function assignToCurrentUser(FlaggedTransaction $flaggedTransaction, User $user): void
    {
        DB::transaction(function () use ($flaggedTransaction, $user) {
            $locked = FlaggedTransaction::whereKey($flaggedTransaction->id)->lockForUpdate()->firstOrFail();

            $oldStatus = $locked->status;
            $oldAssignedTo = $locked->assigned_to;

            $locked->update([
                'assigned_to' => $user->id,
                'status' => FlagStatus::UnderReview->value,
            ]);

            $this->auditService->logFlaggedTransactionEvent(
                'compliance_flag_assigned',
                $locked->id,
                [
                    'user_id' => $user->id,
                    'old_values' => [
                        'status' => $oldStatus,
                        'assigned_to' => $oldAssignedTo,
                    ],
                    'new_values' => [
                        'status' => FlagStatus::UnderReview->value,
                        'assigned_to' => $user->id,
                        'assigned_by' => $user->username,
                    ],
                ],
                'WARNING'
            );
        });

        DB::afterCommit(fn () => $this->cacheInvalidationService->invalidate('dashboard'));
    }

    public function resolve(FlaggedTransaction $flaggedTransaction, User $user): void
    {
        DB::transaction(function () use ($flaggedTransaction, $user) {
            $locked = FlaggedTransaction::whereKey($flaggedTransaction->id)->lockForUpdate()->firstOrFail();

            // Idempotent: a second resolve racing the first must not write a
            // duplicate audit record.
            if ($locked->status === FlagStatus::Resolved) {
                return;
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => FlagStatus::Resolved->value,
                'reviewed_by' => $user->id,
                'resolved_at' => now(),
            ]);

            $this->auditService->logFlaggedTransactionEvent(
                'compliance_flag_resolved',
                $locked->id,
                [
                    'user_id' => $user->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => [
                        'status' => FlagStatus::Resolved->value,
                        'reviewed_by' => $user->id,
                        'reviewed_by_username' => $user->username,
                        'resolved_at' => now()->toDateTimeString(),
                    ],
                ],
                'INFO'
            );
        });

        DB::afterCommit(fn () => $this->cacheInvalidationService->invalidate('dashboard'));
    }

    /**
     * Get compliance flag status counts for dashboard stats.
     *
     * @return array{open: int, under_review: int, resolved_today: int, high_priority: int}
     */
    public function getStatusCounts(): array
    {
        // Flag writes flush the 'dashboard' tag, so stale counts cannot
        // outlive the TTL.
        return $this->cacheOptimizationService->remember(
            CacheKeys::ComplianceFlagStatusCounts->value,
            60,
            ['dashboard'],
            function () {
                $counts = FlaggedTransaction::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status');

                return [
                    'open' => $counts->get(FlagStatus::Open->value, 0),
                    'under_review' => $counts->get(FlagStatus::UnderReview->value, 0),
                    'resolved_today' => FlaggedTransaction::where('status', FlagStatus::Resolved->value)
                        ->whereBetween('resolved_at', [today()->startOfDay(), today()->endOfDay()])
                        ->count(),
                    'high_priority' => FlaggedTransaction::highPriority()->count(),
                ];
            }
        );
    }
}
