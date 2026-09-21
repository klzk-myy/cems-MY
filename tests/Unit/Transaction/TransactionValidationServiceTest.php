<?php

namespace Tests\Unit\Transaction;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Screening\CustomerScreeningService;
use App\Services\Security\IpValidationService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionHoldService;
use App\Services\Transaction\TransactionValidationService;
use App\ValueObjects\RiskAnalysisResult;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

trait CreatesScreeningMock
{
    protected function createScreeningMock(string $action = 'clear'): MockObject
    {
        $screeningMock = $this->createMock(CustomerScreeningService::class);
        $screeningMock->method('screenCustomer')
            ->willReturn(new ScreeningResponse(
                action: $action,
                confidenceScore: $action === 'clear' ? 0.0 : 95.0,
                matches: new Collection,
                screenedAt: Carbon::now()
            ));

        return $screeningMock;
    }
}

class TransactionValidationServiceTest extends TestCase
{
    use CreatesScreeningMock, RefreshDatabase;

    #[Test]
    public function pre_validate_calls_hold_service_with_correct_params(): void
    {
        $customer = Customer::factory()->create();

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Standard);

        $holdMock = $this->createMock(TransactionHoldService::class);
        $holdMock->expects($this->once())
            ->method('requiresHold')
            ->with(CddLevel::Standard, [])
            ->willReturn(false);

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock(),
            $this->createMock(HistoricalRiskAnalysisService::class),
            $this->createMock(AuditService::class),
            $holdMock,
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $service->preValidate($customer, '1000.00', 'MYR');
    }

    #[Test]
    public function pre_validate_sets_hold_required_based_on_hold_service(): void
    {
        $customer = Customer::factory()->create();

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Enhanced);

        $holdMock = $this->createMock(TransactionHoldService::class);
        $holdMock->method('requiresHold')
            ->willReturn(true);

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock(),
            $this->createMock(HistoricalRiskAnalysisService::class),
            $this->createMock(AuditService::class),
            $holdMock,
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $result = $service->preValidate($customer, '1000.00', 'MYR');

        $this->assertTrue($result->isHoldRequired());
    }

    #[Test]
    public function pre_validate_adds_risk_flags_when_returning_customer(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subDays(2),
        ]);

        $riskResult = new RiskAnalysisResult;
        $riskResult->addFlag(['type' => 'velocity', 'severity' => 'low']);

        $riskAnalysisMock = $this->createMock(HistoricalRiskAnalysisService::class);
        $riskAnalysisMock->method('analyze')
            ->willReturn($riskResult);

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Standard);

        $holdMock = $this->createMock(TransactionHoldService::class);
        $holdMock->method('requiresHold')
            ->willReturn(false);

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock(),
            $riskAnalysisMock,
            $this->createMock(AuditService::class),
            $holdMock,
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $result = $service->preValidate($customer, '1000.00', 'MYR');

        $this->assertNotEmpty($result->getRiskFlags());
    }

    #[Test]
    public function pre_validate_no_risk_flags_when_new_customer(): void
    {
        $customer = Customer::factory()->create();

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Standard);

        $holdMock = $this->createMock(TransactionHoldService::class);
        $holdMock->method('requiresHold')
            ->willReturn(false);

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock(),
            $this->createMock(HistoricalRiskAnalysisService::class),
            $this->createMock(AuditService::class),
            $holdMock,
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $result = $service->preValidate($customer, '1000.00', 'MYR');

        $this->assertEmpty($result->getRiskFlags());
    }

    #[Test]
    public function pre_validate_marks_sanctions_flag_as_critical_risk_flag(): void
    {
        $customer = Customer::factory()->create();

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Simplified);

        $holdMock = $this->createMock(TransactionHoldService::class);
        $holdMock->expects($this->once())
            ->method('requiresHold')
            ->with(
                CddLevel::Simplified,
                $this->callback(fn (array $flags) => collect($flags)->contains(
                    fn ($flag) => ($flag['type'] ?? null) === 'sanctions_flag'
                        && ($flag['severity'] ?? null) === 'critical'
                ))
            )
            ->willReturn(true);

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock('flag'),
            $this->createMock(HistoricalRiskAnalysisService::class),
            $this->createMock(AuditService::class),
            $holdMock,
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $result = $service->preValidate($customer, '1000.00', 'MYR');

        $this->assertFalse($result->isBlocked());
        $this->assertTrue($result->isHoldRequired());
        $this->assertNotEmpty($result->getRiskFlags());
    }

    #[Test]
    public function pre_validate_audit_logged(): void
    {
        $customer = Customer::factory()->create();

        $complianceMock = $this->createMock(ComplianceService::class);
        $complianceMock->method('determineCDDLevel')
            ->willReturn(CddLevel::Simplified);

        $auditMock = $this->createMock(AuditService::class);
        $auditMock->expects($this->once())
            ->method('logPreTransactionEvent')
            ->with(
                'pre_validation_completed',
                $customer->id,
                $this->callback(fn ($ctx) => $ctx['new_values']['customer_id'] === $customer->id),
                'INFO'
            );

        $service = new TransactionValidationService(
            $complianceMock,
            new ThresholdService,
            $this->createMock(TellerAllocationService::class),
            $this->createMock(PepApprovalService::class),
            $this->createScreeningMock(),
            $this->createMock(HistoricalRiskAnalysisService::class),
            $auditMock,
            $this->createMock(TransactionHoldService::class),
            app(TillBalanceManager::class),
            app(IpValidationService::class),
        );

        $service->preValidate($customer, '1000.00', 'MYR');
    }
}
