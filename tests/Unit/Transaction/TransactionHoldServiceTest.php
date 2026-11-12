<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Services\Transaction\TransactionHoldService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionHoldServiceTest extends TestCase
{
    protected TransactionHoldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionHoldService;
    }

    #[Test]
    public function enhanced_cdd_requires_hold(): void
    {
        $result = $this->service->requiresHold(CddLevel::Enhanced, []);

        $this->assertTrue($result);
    }

    #[Test]
    public function standard_cdd_no_hold_by_default(): void
    {
        $result = $this->service->requiresHold(CddLevel::Standard, []);

        $this->assertFalse($result);
    }

    #[Test]
    public function simplified_cdd_no_hold_by_default(): void
    {
        $result = $this->service->requiresHold(CddLevel::Simplified, []);

        $this->assertFalse($result);
    }

    #[Test]
    public function hold_triggered_by_critical_risk_flag(): void
    {
        $riskFlags = [
            ['type' => 'velocity', 'severity' => 'medium'],
            ['type' => 'structuring', 'severity' => 'critical'],
        ];

        $result = $this->service->requiresHold(CddLevel::Standard, $riskFlags);

        $this->assertTrue($result);
    }

    #[Test]
    public function hold_not_triggered_by_non_critical_flags(): void
    {
        $riskFlags = [
            ['type' => 'velocity', 'severity' => 'low'],
            ['type' => 'round_trip', 'severity' => 'medium'],
        ];

        $result = $this->service->requiresHold(CddLevel::Standard, $riskFlags);

        $this->assertFalse($result);
    }
}
