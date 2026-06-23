<?php

namespace Tests\Unit\Services;

use App\Services\DTOs\AllocationValidationResult;
use App\Services\DTOs\ComplianceCheckResult;
use App\Services\DTOs\RateOverrideResult;
use App\Services\DTOs\ValidationResult;
use Tests\TestCase;

class DTOTest extends TestCase
{
    public function test_validation_result_holds_data(): void
    {
        $result = new ValidationResult(
            valid: true,
            errors: [],
            warnings: ['Low balance']
        );

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertContains('Low balance', $result->warnings);
    }

    public function test_allocation_validation_result(): void
    {
        $result = new AllocationValidationResult(
            valid: false,
            reason: 'Exceeds daily limit',
            allocation: null
        );

        $this->assertFalse($result->valid);
        $this->assertEquals('Exceeds daily limit', $result->reason);
        $this->assertNull($result->allocation);
    }

    public function test_compliance_check_result(): void
    {
        $result = new ComplianceCheckResult(
            requiresHold: true,
            reasons: ['High risk customer', 'Large amount'],
            cddLevel: 'Enhanced'
        );

        $this->assertTrue($result->requiresHold);
        $this->assertCount(2, $result->reasons);
        $this->assertEquals('Enhanced', $result->cddLevel);
    }

    public function test_rate_override_result(): void
    {
        $result = new RateOverrideResult(
            success: true,
            message: 'Rate updated',
            previousRate: '4.5000',
            newRate: '4.5500'
        );

        $this->assertTrue($result->success);
        $this->assertEquals('4.5000', $result->previousRate);
    }
}
