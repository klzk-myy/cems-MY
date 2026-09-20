<?php

namespace Tests\Feature\Audit;

use App\Exceptions\Domain\AccountingPeriodException;
use App\Exceptions\Domain\AuditIntegrityException;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\SystemAlert;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\AuditService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 10 regression coverage: audit-chain quarantine (AU1), unsealed
 * watchdog alerting (AU2) and journal-line one-sidedness (A1).
 */
class AuditChainQuarantineTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
    }

    private function makeLog(string $action = 'test_action'): SystemLog
    {
        return SystemLog::create([
            'user_id' => User::factory()->create()->id,
            'action' => $action,
            'severity' => 'INFO',
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function unsealed_predecessor_still_defers_seal(): void
    {
        $a = $this->makeLog('a');
        $b = $this->makeLog('b');
        $c = $this->makeLog('c');

        $this->assertTrue($this->auditService->sealLogEntry($a->id));

        // b sits unsealed between a and c: c must not seal across a live gap.
        $this->assertFalse($this->auditService->sealLogEntry($c->id));
        $this->assertNull($c->fresh()->entry_hash);
    }

    #[Test]
    public function seal_quarantine_does_not_stall_chain(): void
    {
        $a = $this->makeLog('a');
        $bad = $this->makeLog('poisoned');
        $c = $this->makeLog('c');

        $this->assertTrue($this->auditService->sealLogEntry($a->id));

        // The poisoned row sits unsealed; c cannot seal across it yet.
        $this->assertFalse($this->auditService->sealLogEntry($c->id));

        // After N failed attempts the blocker is quarantined…
        $this->auditService->quarantineEntry($bad->id);
        $this->assertSame('quarantined', $bad->fresh()->seal_status);

        // …and c seals with an explicit gap boundary marker.
        $this->assertTrue($this->auditService->sealLogEntry($c->id));
        $this->assertSame(AuditService::GAP_PREFIX.$bad->id, $c->fresh()->previous_hash);

        // Quarantined rows are terminal: not counted as pending.
        $this->assertSame(0, $this->auditService->getUnsealedCount());
    }

    #[Test]
    public function verify_chain_integrity_reports_quarantine_boundaries(): void
    {
        $a = $this->makeLog('a');
        $bad = $this->makeLog('poisoned');
        $c = $this->makeLog('c');
        $d = $this->makeLog('d');

        $this->auditService->sealLogEntry($a->id);
        $this->auditService->quarantineEntry($bad->id);
        $this->auditService->sealLogEntry($c->id);
        $this->auditService->sealLogEntry($d->id);

        $result = $this->auditService->verifyChainIntegrity();

        $this->assertTrue($result['valid']);
        $this->assertSame(
            [['entry_id' => $c->id, 'quarantined_id' => $bad->id]],
            $result['quarantine_boundaries']
        );
        // d links normally to c's real hash — the chain continues past the gap.
        $this->assertSame($c->fresh()->entry_hash, $d->fresh()->previous_hash);
    }

    #[Test]
    public function seal_job_quarantines_blocking_row_on_permanent_failure(): void
    {
        Queue::fake();

        $a = $this->makeLog('a');
        $bad = $this->makeLog('poisoned');
        $c = $this->makeLog('c');

        $this->auditService->sealLogEntry($a->id);

        $job = new SealAuditHashJob($c->id);

        // The live handle() throws on the gap (retries in production);
        // after tries are exhausted Laravel invokes failed().
        try {
            $job->handle($this->auditService);
            $this->fail('expected gap exception');
        } catch (AuditIntegrityException $e) {
            $job->failed($e);
        }

        $this->assertSame('quarantined', $bad->fresh()->seal_status);
        Queue::assertPushed(SealAuditHashJob::class, fn ($j) => $j->logId === $c->id);

        // The re-dispatched job now seals c across the gap boundary.
        $job->handle($this->auditService);
        $this->assertSame(AuditService::GAP_PREFIX.$bad->id, $c->fresh()->previous_hash);
        $this->assertNotNull($c->fresh()->entry_hash);
    }

    #[Test]
    public function sweeper_quarantines_a_row_that_throws_on_every_attempt(): void
    {
        $a = $this->makeLog('a');
        $bad = $this->makeLog('poisoned');
        $c = $this->makeLog('c');

        foreach ([$a, $bad, $c] as $log) {
            DB::table('system_logs')->where('id', $log->id)
                ->update(['created_at' => now()->subHour()]);
        }

        $this->auditService->sealLogEntry($a->id);

        // A row whose own seal always throws is the stall mode that needs
        // quarantine. Mock just that id; other ids use the real service.
        $real = $this->auditService;
        $mock = \Mockery::mock(AuditService::class)->makePartial();
        $mock->shouldReceive('sealLogEntry')->andReturnUsing(
            fn (int $id) => $id === $bad->id
                ? throw new \RuntimeException('corrupted payload')
                : $real->sealLogEntry($id)
        );
        $this->instance(AuditService::class, $mock);

        // Sweep 1: bad row fails attempt 1; c defers on the gap.
        $this->assertSame(0, Artisan::call('audit:seal-pending', ['--older-than' => 30, '--attempts' => 2]));
        $this->assertNull($bad->fresh()->seal_status);
        $this->assertNull($c->fresh()->entry_hash);

        // Sweep 2: bad row hits the attempt ceiling and quarantines; c
        // seals across it in the same pass.
        $this->assertSame(0, Artisan::call('audit:seal-pending', ['--older-than' => 30, '--attempts' => 2]));

        $this->assertSame('quarantined', $bad->fresh()->seal_status);
        $this->assertSame(AuditService::GAP_PREFIX.$bad->id, $c->fresh()->previous_hash);
    }

    #[Test]
    public function watch_unsealed_alerts_once_per_incident(): void
    {
        $log = $this->makeLog('stuck');
        DB::table('system_logs')->where('id', $log->id)
            ->update(['created_at' => now()->subHours(3)]);

        $this->assertSame(0, Artisan::call('audit:watch-unsealed', ['--count' => 0, '--age' => 60]));

        $this->assertSame(1, SystemAlert::where('source', 'audit_chain')->count());

        // Second run while the alert is unacknowledged: deduped.
        $this->assertSame(0, Artisan::call('audit:watch-unsealed', ['--count' => 0, '--age' => 60]));

        $this->assertSame(1, SystemAlert::where('source', 'audit_chain')->count());
    }

    #[Test]
    public function journal_line_with_both_debit_and_credit_rejected(): void
    {
        $service = new AccountingService(new MathService, new AuditService, new CacheInvalidationService);

        $this->expectException(AccountingPeriodException::class);
        $this->expectExceptionMessage('exactly one of debit or credit');

        $service->createJournalEntry(
            [
                ['account_code' => '1010', 'debit' => '100.00', 'credit' => '50.00'],
                ['account_code' => '4010', 'debit' => '0', 'credit' => '50.00'],
            ],
            'Manual'
        );
    }

    #[Test]
    public function journal_line_with_neither_debit_nor_credit_rejected(): void
    {
        $service = new AccountingService(new MathService, new AuditService, new CacheInvalidationService);

        $this->expectException(AccountingPeriodException::class);
        $this->expectExceptionMessage('exactly one of debit or credit');

        $service->createJournalEntry(
            [
                ['account_code' => '1010', 'debit' => '100.00', 'credit' => '0'],
                ['account_code' => '4010', 'debit' => '0', 'credit' => '0'],
                ['account_code' => '4010', 'debit' => '0', 'credit' => '100.00'],
            ],
            'Manual'
        );
    }
}
