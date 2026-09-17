<?php

namespace Tests\Unit\Jobs\Audit;

use App\Exceptions\Domain\AuditIntegrityException;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SealAuditHashJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seals_an_unsealed_log_entry_with_hash_chain()
    {
        // Arrange: create users and log entries, first already sealed
        $auditService = $this->mock(AuditService::class);
        $auditService->shouldReceive('computeEntryHash')
            ->andReturnUsing(function ($createdAt, $userId, $action, $entityType, $entityId, $previousHash) {
                return hash('sha256', $createdAt.$userId.$action.$entityType.$entityId.$previousHash);
            });

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $log1 = SystemLog::create([
            'created_at' => now()->subMinute(),
            'user_id' => $user1->id,
            'action' => 'test_action_1',
            'entity_type' => 'Test',
            'entity_id' => 1,
            'entry_hash' => 'hash1',
            'previous_hash' => null,
        ]);

        $log2 = SystemLog::create([
            'created_at' => now(),
            'user_id' => $user2->id,
            'action' => 'test_action_2',
            'entity_type' => 'Test',
            'entity_id' => 2,
            'entry_hash' => null,
            'previous_hash' => null,
        ]);

        // Act: run the job for log2
        $job = new SealAuditHashJob($log2->id);
        $job->handle($auditService);

        // Assert: log2 is now sealed with hash and previous_hash set
        $refreshed = $log2->fresh();
        $this->assertNotNull($refreshed->entry_hash);
        $this->assertNotNull($refreshed->previous_hash);
        $this->assertSame('hash1', $refreshed->previous_hash);
    }

    #[Test]
    public function it_does_nothing_if_log_already_sealed()
    {
        $auditService = $this->mock(AuditService::class);
        $auditService->shouldNotReceive('computeEntryHash');

        $user = User::factory()->create();

        $log = SystemLog::create([
            'created_at' => now(),
            'user_id' => $user->id,
            'action' => 'test',
            'entity_type' => 'Test',
            'entity_id' => 1,
            'entry_hash' => 'existing_hash',
            'previous_hash' => 'prev_hash',
        ]);

        $job = new SealAuditHashJob($log->id);
        $job->handle($auditService);

        // The hash should remain unchanged
        $refreshed = $log->fresh();
        $this->assertSame('existing_hash', $refreshed->entry_hash);
        $this->assertSame('prev_hash', $refreshed->previous_hash);
    }

    #[Test]
    public function it_seals_first_log_without_predecessor()
    {
        $auditService = $this->mock(AuditService::class);
        $auditService->shouldReceive('computeEntryHash')
            ->andReturn('first_hash');

        $user = User::factory()->create();

        $log = SystemLog::create([
            'created_at' => now(),
            'user_id' => $user->id,
            'action' => 'first_action',
            'entity_type' => 'Test',
            'entity_id' => 1,
            'entry_hash' => null,
            'previous_hash' => null,
        ]);

        $job = new SealAuditHashJob($log->id);
        $job->handle($auditService);

        $refreshed = $log->fresh();
        $this->assertSame('first_hash', $refreshed->entry_hash);
        $this->assertNull($refreshed->previous_hash);
    }

    #[Test]
    public function it_retries_when_the_log_row_is_not_visible_yet(): void
    {
        // A pickup ahead of the writing transaction's commit (a connector
        // without after_commit) must fail so the backoff ladder retries —
        // returning silently would leave the committed entry unsealed.
        $auditService = $this->mock(AuditService::class);
        $auditService->shouldNotReceive('computeEntryHash');

        $job = new SealAuditHashJob(999999);

        $this->expectException(AuditIntegrityException::class);
        $job->handle($auditService);
    }

    #[Test]
    public function it_dispatches_after_commit_regardless_of_connection_config(): void
    {
        $this->assertInstanceOf(
            ShouldQueueAfterCommit::class,
            new SealAuditHashJob(1)
        );
    }

    #[Test]
    public function seal_pending_command_seals_stale_unsealed_entries_in_order(): void
    {
        $user = User::factory()->create();

        // Rows written directly (no SealAuditHashJob dispatch) stay unsealed —
        // the sweeper is the repair path for exactly this state. created_at is
        // not fillable, so the stale timestamps are applied via forceFill.
        $log1 = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'stale_first',
            'entry_hash' => null,
            'previous_hash' => null,
        ]);
        $log2 = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'stale_second',
            'entry_hash' => null,
            'previous_hash' => null,
        ]);
        $tooFresh = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'in_flight',
            'entry_hash' => null,
            'previous_hash' => null,
        ]);

        $log1->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $log2->forceFill(['created_at' => now()->subMinutes(9)])->save();

        $this->artisanCommand('audit:seal-pending')->assertSuccessful();

        // Both stale rows sealed, second chained onto the first; the fresh
        // row is left for the in-flight seal job.
        $this->assertNotNull($log1->refresh()->entry_hash);
        $this->assertSame($log1->entry_hash, $log2->refresh()->previous_hash);
        $this->assertNull($tooFresh->refresh()->entry_hash);
    }
}
