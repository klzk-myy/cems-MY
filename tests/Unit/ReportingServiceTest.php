<?php

namespace Tests\Unit;

use App\Services\Reporting\ReportingService;
use App\Services\System\EncryptionService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    private ReportingService $service;

    private ThresholdService $thresholdService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->thresholdService = new ThresholdService;
        $mathService = new MathService;
        $encryptionService = new EncryptionService;

        $this->service = new ReportingService(
            $encryptionService,
            $mathService,
            $this->thresholdService
        );
    }

    #[Test]
    public function service_is_instantiated(): void
    {
        $this->assertInstanceOf(ReportingService::class, $this->service);
    }

    #[Test]
    public function threshold_service_integration_for_str(): void
    {
        $strThreshold = $this->thresholdService->getStrThreshold();
        $this->assertEquals('50000', $strThreshold);
    }

    #[Test]
    public function threshold_service_integration_for_edd(): void
    {
        $eddThreshold = $this->thresholdService->getEddThreshold();
        $this->assertEquals('50000', $eddThreshold);
    }

    #[Test]
    public function all_reporting_thresholds_return_string(): void
    {
        $this->assertIsString($this->thresholdService->getStrThreshold());
        $this->assertIsString($this->thresholdService->getEddThreshold());
    }
}
