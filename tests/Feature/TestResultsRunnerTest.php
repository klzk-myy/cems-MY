<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TestResult;
use App\Models\User;
use App\Services\System\TestRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test-results dashboard actions: cleanup of aged runs and the run trigger.
 * The runner itself is mocked for the run test — this pins the HTTP surface
 * (auth, redirect, service delegation), not the subprocess execution.
 */
class TestResultsRunnerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    #[Test]
    public function cleanup_deletes_only_results_older_than_the_cutoff(): void
    {
        $stale = TestResult::factory()->create(['created_at' => now()->subDays(120)]);
        $fresh = TestResult::factory()->create(['created_at' => now()->subDays(10)]);

        $this->actingAs($this->admin)
            ->post(route('test-results.cleanup'), ['days' => '90'])
            ->assertRedirect(route('test-results.index'))
            ->assertSessionHas('success', 'Cleaned up 1 old test results');

        $this->assertDatabaseMissing('test_results', ['id' => $stale->id]);
        $this->assertDatabaseHas('test_results', ['id' => $fresh->id]);
    }

    #[Test]
    public function run_delegates_to_the_runner_and_redirects_to_the_result(): void
    {
        $result = TestResult::factory()->create();

        $this->mock(TestRunnerService::class)
            ->shouldReceive('runTests')
            ->once()
            ->with('unit', [])
            ->andReturn($result);

        $this->actingAs($this->admin)
            ->post(route('test-results.run'), ['suite' => 'unit'])
            ->assertRedirect(route('test-results.show', $result))
            ->assertSessionHas('success', 'unit tests completed');
    }

    #[Test]
    public function non_privileged_users_cannot_view_the_test_dashboard(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->actingAs($teller)
            ->post(route('test-results.cleanup'), ['days' => '90'])
            ->assertForbidden();
    }
}
