<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonConfigurationTest extends TestCase
{
    #[Test]
    public function horizon_has_optimized_configuration()
    {
        $config = config('horizon.environments.production');

        $this->assertArrayHasKey('supervisor-1', $config);
        $supervisor = $config['supervisor-1'];

        $this->assertEquals('redis', $supervisor['connection']);
        $this->assertEquals(['high', 'default', 'low'], $supervisor['queue']);
        $this->assertEquals('auto', $supervisor['balance']);
        $this->assertGreaterThanOrEqual(5, $supervisor['maxProcesses']);
        $this->assertGreaterThanOrEqual(3600, $supervisor['timeout']);
    }

    #[Test]
    public function horizon_trim_settings_are_configured()
    {
        $trim = config('horizon.trim');

        $this->assertArrayHasKey('recent', $trim);
        $this->assertArrayHasKey('failed', $trim);
        $this->assertArrayHasKey('monitored', $trim);

        $this->assertGreaterThanOrEqual(60, $trim['recent']);
        $this->assertGreaterThanOrEqual(10080, $trim['failed']);
    }
}
