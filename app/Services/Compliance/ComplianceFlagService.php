<?php

namespace App\Services\Compliance;

use App\Models\FlaggedTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\System\CacheTagsService;

class ComplianceFlagService
{
    public function __construct(
        protected AuditService $auditService,
        protected CacheTagsService $cacheTagsService,
    ) {}

    public function assignToCurrentUser(FlaggedTransaction $flaggedTransaction, User $user): void
    {
        $oldStatus = $flaggedTransaction->status;
        $oldAssignedTo = $flaggedTransaction->assigned_to;

        $flaggedTransaction->update([
            'assigned_to' => $user->id,
            'status' => 'Under_Review',
        ]);

        $this->cacheTagsService->invalidate('dashboard');

        $this->auditService->logWithSeverity(
            'compliance_flag_assigned',
            [
                'user_id' => $user->id,
                'entity_type' => 'FlaggedTransaction',
                'entity_id' => $flaggedTransaction->id,
                'old_values' => [
                    'status' => $oldStatus,
                    'assigned_to' => $oldAssignedTo,
                ],
                'new_values' => [
                    'status' => 'Under_Review',
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
            'status' => 'Resolved',
            'reviewed_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->cacheTagsService->invalidate('dashboard');

        $this->auditService->logWithSeverity(
            'compliance_flag_resolved',
            [
                'user_id' => $user->id,
                'entity_type' => 'FlaggedTransaction',
                'entity_id' => $flaggedTransaction->id,
                'old_values' => [
                    'status' => $oldStatus,
                ],
                'new_values' => [
                    'status' => 'Resolved',
                    'reviewed_by' => $user->id,
                    'reviewed_by_username' => $user->username,
                    'resolved_at' => now()->toDateTimeString(),
                ],
            ],
            'INFO'
        );
    }
}
