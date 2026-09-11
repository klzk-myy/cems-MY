<?php

namespace Tests\Unit\System;

use App\Enums\TestResultStatus;
use App\Models\TestResult;
use App\Services\System\TestRunnerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestRunnerServiceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function build_command_escapes_shell_parameters(): void
    {
        $service = new TestRunnerService;

        $reflection = new \ReflectionMethod($service, 'buildCommand');
        $reflection->setAccessible(true);

        // Suite names are whitelist-validated (this is the injection guard):
        // a value containing shell metacharacters must be rejected outright.
        try {
            $reflection->invoke($service, 'unit; rm -rf /', []);
            $this->fail('Expected InvalidArgumentException for an unknown test suite');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid test suite', $e->getMessage());
        }

        // Known suites render the filter as a single quoted argument so even a
        // future change cannot chain commands through the filter value.
        $command = $reflection->invoke($service, 'Transaction', []);
        $this->assertStringNotContainsString('; ', $command, 'No semicolon should appear outside quotes');
        $this->assertStringContainsString("php artisan test --filter='Transaction'", $command);
    }

    #[Test]
    public function get_statistics_counts_enum_cast_statuses_correctly(): void
    {
        // Regression: status is enum-cast (TestResultStatus), so Collection
        // filters must compare against the enum instance, not a raw string.
        // String comparisons ('passed'/'failed') always returned zero counts.
        TestResult::factory()->create(['status' => TestResultStatus::Passed]);
        TestResult::factory()->create(['status' => TestResultStatus::Passed]);
        TestResult::factory()->create(['status' => TestResultStatus::Failed]);
        TestResult::factory()->create(['status' => TestResultStatus::Error]);

        $service = new TestRunnerService;
        $stats = $service->getStatistics(30);

        $this->assertSame(4, $stats['total_runs']);
        $this->assertSame(2, $stats['passed']);
        $this->assertSame(1, $stats['failed']);
    }
}
