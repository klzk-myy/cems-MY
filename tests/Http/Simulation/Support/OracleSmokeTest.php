<?php

namespace Tests\Http\Simulation\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Smoke test for the Task 1 harness scaffolding.
 *
 * Verifies the seeded simulation database is readable through the oracle and
 * that both clients can be constructed against the in-process requesters.
 */
class OracleSmokeTest extends SimulationTestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reads_seeded_simulation_db(): void
    {
        $oracle = new SimulationOracle(DB::connection());
        $tables = $oracle->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $names = array_column($tables, 'name');

        $this->assertContains('transactions', $names);
        $this->assertContains('customers', $names);
        $this->assertContains('journal_entries', $names);
    }

    #[Test]
    public function it_can_build_both_clients(): void
    {
        $web = $this->newWebClient();
        $api = $this->newApiClient('fake-token');

        $this->assertInstanceOf(WebClient::class, $web);
        $this->assertInstanceOf(ApiClient::class, $api);
    }
}
