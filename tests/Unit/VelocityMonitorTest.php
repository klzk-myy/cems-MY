<?php

namespace Tests\Unit;

use App\Services\Compliance\Monitors\VelocityMonitor;
use App\Services\MathService;
use App\Services\Risk\VelocityRiskService;
use App\Services\ThresholdService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VelocityMonitorTest extends TestCase
{
    #[Test]
    public function uses_velocity_window_days_from_threshold_service(): void
    {
        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $velocityRiskService = new VelocityRiskService($mathService, $thresholdService);

        $monitor = new VelocityMonitor($mathService, $velocityRiskService, $thresholdService);

        // ThresholdService::getVelocityWindowDays() defaults to 90 days
        $this->assertEquals(90, $thresholdService->getVelocityWindowDays());

        // The monitor should use the same value
        $reflection = new \ReflectionClass($monitor);
        $property = $reflection->getProperty('velocityWindowDays');
        $property->setAccessible(true);
        $this->assertEquals(90, $property->getValue($monitor));
    }

    #[Test]
    public function velocity_window_is_configurable(): void
    {
        config(['thresholds.velocity.window_days' => 30]);

        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $velocityRiskService = new VelocityRiskService($mathService, $thresholdService);

        $monitor = new VelocityMonitor($mathService, $velocityRiskService, $thresholdService);

        // When config is set to 30, the monitor should use 30
        $this->assertEquals(30, $thresholdService->getVelocityWindowDays());

        $reflection = new \ReflectionClass($monitor);
        $property = $reflection->getProperty('velocityWindowDays');
        $property->setAccessible(true);
        $this->assertEquals(30, $property->getValue($monitor));

        // Reset config
        config(['thresholds.velocity.window_days' => 90]);
    }

    #[Test]
    public function no_longer_uses_hardcoded_lookback_hours(): void
    {
        // Verify the LOOKBACK_HOURS constant no longer exists
        $reflection = new \ReflectionClass(VelocityMonitor::class);

        $this->assertFalse($reflection->hasConstant('LOOKBACK_HOURS'),
            'VelocityMonitor should not have hardcoded LOOKBACK_HOURS constant');
    }
}
