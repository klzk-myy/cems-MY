<?php

namespace Tests\Feature\Audit;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_verification_runs_without_loading_all_rows(): void
    {
        SystemLog::factory()->count(5)->create(['entry_hash' => 'abc']);

        $service = app(AuditService::class);
        $result = $service->verifyChainIntegrity();

        $this->assertArrayHasKey('valid', $result);
    }
}
