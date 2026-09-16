<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\FlagStatus;
use App\Enums\UserRole;
use App\Models\FlaggedTransaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Compliance\ComplianceFlagService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ComplianceFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService&MockObject $auditService;

    private CacheInvalidationService&MockObject $cacheInvalidationService;

    private ComplianceFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditService = $this->createMock(AuditService::class);
        $this->cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $this->service = new ComplianceFlagService($this->auditService, $this->cacheInvalidationService, new CacheOptimizationService);
    }

    #[Test]
    public function assign_to_current_user_updates_status_and_audits(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $flag = FlaggedTransaction::factory()->open()->create();

        $this->auditService->expects($this->once())
            ->method('logFlaggedTransactionEvent')
            ->with(
                'compliance_flag_assigned',
                $flag->id,
                $this->callback(function (array $data) use ($user) {
                    return $data['user_id'] === $user->id
                        && $data['old_values']['status'] === FlagStatus::Open
                        && $data['old_values']['assigned_to'] === null
                        && $data['new_values']['status'] === FlagStatus::UnderReview->value
                        && $data['new_values']['assigned_to'] === $user->id
                        && $data['new_values']['assigned_by'] === $user->username;
                }),
                'WARNING'
            );

        $this->cacheInvalidationService->expects($this->once())
            ->method('invalidate')
            ->with('dashboard');

        $this->service->assignToCurrentUser($flag, $user);

        $flag->refresh();
        $this->assertSame($user->id, $flag->assigned_to);
        $this->assertSame(FlagStatus::UnderReview, $flag->status);
    }

    #[Test]
    public function resolve_updates_status_and_audits(): void
    {
        $user = User::factory()->create(['role' => UserRole::ComplianceOfficer]);
        $flag = FlaggedTransaction::factory()->underReview()->create([
            'assigned_to' => $user->id,
        ]);

        $this->auditService->expects($this->once())
            ->method('logFlaggedTransactionEvent')
            ->with(
                'compliance_flag_resolved',
                $flag->id,
                $this->callback(function (array $data) use ($user) {
                    return $data['user_id'] === $user->id
                        && $data['old_values']['status'] === FlagStatus::UnderReview
                        && $data['new_values']['status'] === FlagStatus::Resolved->value
                        && $data['new_values']['reviewed_by'] === $user->id
                        && $data['new_values']['reviewed_by_username'] === $user->username
                        && isset($data['new_values']['resolved_at']);
                }),
                'INFO'
            );

        $this->cacheInvalidationService->expects($this->once())
            ->method('invalidate')
            ->with('dashboard');

        $this->service->resolve($flag, $user);

        $flag->refresh();
        $this->assertSame($user->id, $flag->reviewed_by);
        $this->assertSame(FlagStatus::Resolved, $flag->status);
        $this->assertNotNull($flag->resolved_at);
    }

    #[Test]
    public function assign_invalidates_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $flag = FlaggedTransaction::factory()->open()->create();

        $this->cacheInvalidationService->expects($this->once())
            ->method('invalidate')
            ->with('dashboard');

        $this->service->assignToCurrentUser($flag, $user);
    }

    #[Test]
    public function resolve_invalidates_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $flag = FlaggedTransaction::factory()->underReview()->create();

        $this->cacheInvalidationService->expects($this->once())
            ->method('invalidate')
            ->with('dashboard');

        $this->service->resolve($flag, $user);
    }
}
