<?php

namespace Tests\Unit\System;

use App\Services\System\TestRunnerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestRunnerServiceTest extends TestCase
{
    #[Test]
    public function build_command_escapes_shell_parameters(): void
    {
        $service = new TestRunnerService;

        $reflection = new \ReflectionMethod($service, 'buildCommand');
        $reflection->setAccessible(true);

        // Test with malicious suite name containing command injection
        $command = $reflection->invoke($service, 'unit; rm -rf /', []);

        // The suite should be passed as a single quoted argument to --filter, e.g. --filter='unit; rm -rf /'
        $this->assertStringContainsString("--filter='unit; rm -rf /'", $command, 'Suite should be escaped as a single quoted string');

        // Ensure there is no unquoted semicolon that could be used for command chaining
        $this->assertStringNotContainsString("; '", $command, 'No semicolon should appear outside quotes');
    }
}
