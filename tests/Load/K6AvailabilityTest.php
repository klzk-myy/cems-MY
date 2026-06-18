<?php

namespace Tests\Load;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class K6AvailabilityTest extends TestCase
{
    #[Test]
    public function k6_is_available(): void
    {
        $result = shell_exec('which k6 2>/dev/null');

        if (empty($result)) {
            $this->markTestSkipped('k6 not installed - install from https://k6.io/');
        }

        $this->assertNotEmpty($result, 'k6 should be installed for load testing');
    }
}
