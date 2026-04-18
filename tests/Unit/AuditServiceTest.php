<?php

namespace Tests\Unit;

use App\Jobs\Audit\SealAuditHashJob;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService;
    }

    public function test_system_log_can_be_created(): void
    {
        $user = User::factory()->create();

        $log = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'description' => 'Test description',
            'entity_type' => 'TestEntity',
            'entity_id' => 1,
            'severity' => 'INFO',
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals('test_action', $log->action);
    }

    public function test_system_log_chain_integrity(): void
    {
        $user = User::factory()->create();

        $log1 = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'First log',
            'previous_hash' => str_repeat('0', 64),
        ]);

        $log2 = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'transaction_created',
            'description' => 'Second log',
            'previous_hash' => $log1->hash,
        ]);

        $this->assertEquals($log1->hash, $log2->previous_hash);
    }

    public function test_verify_chain_integrity_returns_valid_when_intact(): void
    {
        $user = User::factory()->create();

        // Create a chain of logs
        $previousHash = str_repeat('0', 64);
        for ($i = 0; $i < 3; $i++) {
            SystemLog::create([
                'user_id' => $user->id,
                'action' => 'test_action_'.$i,
                'description' => 'Test log '.$i,
                'previous_hash' => $previousHash,
            ]);
        }

        $result = $this->auditService->verifyChainIntegrity();

        $this->assertTrue($result['valid']);
        $this->assertNull($result['broken_at']);
    }

    public function test_verify_chain_integrity_detects_tampering(): void
    {
        $user = User::factory()->create();

        // Create chain of entries
        $previousHash = null;
        $entryIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $entry = SystemLog::create([
                'user_id' => $user->id,
                'action' => 'test_action_'.$i,
                'description' => 'Test log '.$i,
                'previous_hash' => $previousHash,
            ]);
            $entryIds[] = $entry->id;
            $previousHash = $entry->entry_hash;
        }

        // Tamper with the middle entry's entry_hash directly in DB
        // This simulates someone modifying the stored hash value
        DB::table('system_logs')
            ->where('id', $entryIds[1])
            ->update(['entry_hash' => 'tampered_hash_value']);

        $result = $this->auditService->verifyChainIntegrity();

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['broken_at']);
    }

    public function test_log_with_severity_creates_entry(): void
    {
        $user = User::factory()->create();

        $result = $this->auditService->logWithSeverity(
            'test_action',
            ['key' => 'value'],
            'WARNING'
        );

        $this->assertNotNull($result);
    }

    public function test_session_id_is_recorded(): void
    {
        $user = User::factory()->create();
        $sessionId = session()->getId();

        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'description' => 'Test with session',
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.1',
        ]);

        $log = SystemLog::where('session_id', $sessionId)->first();

        $this->assertNotNull($log);
    }

    public function test_log_contains_ip_address(): void
    {
        $user = User::factory()->create();

        SystemLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'description' => 'Test with IP',
            'ip_address' => '192.168.1.1',
        ]);

        $log = SystemLog::where('ip_address', '192.168.1.1')->first();

        $this->assertNotNull($log);
    }

    public function test_system_log_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $log = SystemLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'description' => 'Test log',
        ]);

        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_audit_hash_sealed_async_by_job(): void
    {
        // Create a first log entry to establish the chain
        $log1 = $this->auditService->logWithSeverity('test_action_first', ['entity_type' => 'Test'], 'INFO');

        // Create a second log entry
        $log2 = $this->auditService->logWithSeverity('test_action_second', ['entity_type' => 'Test'], 'INFO');

        // Entry should have null hash initially
        $this->assertNull($log2->entry_hash);

        // Dispatch and run the seal job synchronously for the second entry
        SealAuditHashJob::dispatchSync($log2->id);

        // Entry should now be sealed with previous_hash pointing to log1's hash
        $log2->refresh();
        $this->assertNotNull($log2->entry_hash);
        $this->assertNotNull($log2->previous_hash);
    }

    public function test_verify_chain_integrity_skips_unsealed_entries(): void
    {
        // Create unsealed log
        $log = $this->auditService->logWithSeverity('test_action', [], 'INFO');

        // Verify doesn't throw even with unsealed entries
        $result = $this->auditService->verifyChainIntegrity();

        // Should pass since unsealed entries are skipped
        $this->assertTrue($result['valid']);
    }
}
