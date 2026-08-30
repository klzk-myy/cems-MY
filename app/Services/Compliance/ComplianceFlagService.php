<?php

namespace App\Services\Compliance;

use App\Enums\FlagStatus;
use App\Models\FlaggedTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;

class ComplianceFlagService
{
    public function __construct(
        protected AuditService $auditService,
        protected CacheInvalidationService $cacheInvalidationService,
    ) {}

    public function assignToCurrentUser(FlaggedTransaction $flaggedTransaction, User $user): void
    {
        $oldStatus = $flaggedTransaction->status;
        $oldAssignedTo = $flaggedTransaction->assigned_to;

        $flaggedTransaction->update([
            'assigned_to' => $user->id,
            'status' => FlagStatus::UnderReview->value,
        ]);

        $this->cacheInvalidationService->invalidate('dashboard');

        $this->auditService->logFlaggedTransactionEvent(
            'compliance_flag_assigned',
            $flaggedTransaction->id,
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
    }

    public function resolve(FlaggedTransaction $flaggedTransaction, User $user): void
    {
        $oldStatus = $flaggedTransaction->status;

        $flaggedTransaction->update([
            'status' => FlagStatus::Resolved->value,
            'reviewed_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->cacheInvalidationService->invalidate('dashboard');

        $this->auditService->logFlaggedTransactionEvent(
            'compliance_flag_resolved',
            $flaggedTransaction->id,
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
    }

    /**
     * Get compliance flag status counts for dashboard stats.
     *
     * @return array{open: int, under_review: int, resolved_today: int, high_priority: int}
     */
    public function getStatusCounts(): array
    {
        $counts = FlaggedTransaction::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'open' => $counts->get('Open', 0),
            'under_review' => $counts->get('Under_Review', 0),
            'resolved_today' => FlaggedTransaction::where('status', 'Resolved')
                ->whereDate('resolved_at', today())
                ->count(),
            'high_priority' => FlaggedTransaction::whereIn('flag_type', ['Sanction_Match', 'Structuring', 'Velocity'])
                ->where('status', '!=', 'Resolved')
                ->count(),
        ];
    }
}
