<?php

namespace Tests\Unit;

use App\Services\EncryptionService;
use App\Services\MathService;
use App\Services\ReportingService;
use App\Services\ThresholdService;
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

    public function test_service_is_instantiated(): void
    {
        $this->assertInstanceOf(ReportingService::class, $this->service);
    }

    public function test_threshold_service_integration_for_lctr(): void
    {
        $ctrThreshold = $this->thresholdService->getCtrThreshold();
        $this->assertEquals('25000', $ctrThreshold);
    }

    public function test_threshold_service_integration_for_ctos(): void
    {
        $ctosThreshold = $this->thresholdService->getCtosThreshold();
        $this->assertEquals('25000', $ctosThreshold);
    }

    public function test_threshold_service_integration_for_str(): void
    {
        $strThreshold = $this->thresholdService->getStrThreshold();
        $this->assertEquals('50000', $strThreshold);
    }

    public function test_threshold_service_integration_for_edd(): void
    {
        $eddThreshold = $this->thresholdService->getEddThreshold();
        $this->assertEquals('50000', $eddThreshold);
    }

    public function test_ctr_threshold_is_used_for_large_transaction_reporting(): void
    {
        $threshold = $this->thresholdService->getCtrThreshold();
        $this->assertEquals('25000', $threshold);
        $this->assertIsString($threshold);
    }

    public function test_ctos_threshold_for_reporting(): void
    {
        $threshold = $this->thresholdService->getCtosThreshold();
        $this->assertEquals('25000', $threshold);
        $this->assertIsString($threshold);
    }

    public function test_all_reporting_thresholds_return_string(): void
    {
        $this->assertIsString($this->thresholdService->getCtrThreshold());
        $this->assertIsString($this->thresholdService->getCtosThreshold());
        $this->assertIsString($this->thresholdService->getStrThreshold());
        $this->assertIsString($this->thresholdService->getEddThreshold());
        $this->assertIsString($this->thresholdService->getLctrThreshold());
    }

    public function test_lctr_threshold_equals_ctr_threshold(): void
    {
        $lctr = $this->thresholdService->getLctrThreshold();
        $ctr = $this->thresholdService->getCtrThreshold();
        $this->assertEquals($ctr, $lctr);
    }
}
