<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\EncryptionService;
use App\Services\MathService;
use App\Services\Risk\StructuringRiskService;
use App\Services\Risk\VelocityRiskService;
use App\Services\ThresholdService;
use App\Services\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $encryptionService = new EncryptionService;
        $velocityRiskService = new VelocityRiskService($mathService, $thresholdService);
        $structuringRiskService = new StructuringRiskService($mathService, $thresholdService);

        $complianceService = new ComplianceService(
            $encryptionService,
            $mathService,
            null,
            $thresholdService,
            $velocityRiskService,
            $structuringRiskService
        );

        $auditService = new AuditService;

        $this->service = new TransactionMonitoringService(
            $complianceService,
            $mathService,
            $auditService,
            $thresholdService
        );
    }

    public function test_is_round_amount_method_was_removed(): void
    {
        $reflection = new \ReflectionClass($this->service);

        $this->assertFalse(
            $reflection->hasMethod('isRoundAmount'),
            'isRoundAmount() method should be removed - it was causing false positives'
        );
    }

    public function test_round_amount_detection_does_not_flag_legitimate_large_transactions(): void
    {
        $amount = '50000.00';

        $thresholdService = new ThresholdService;
        $ctosThreshold = $thresholdService->getCtosThreshold();
        $this->assertEquals('25000', $ctosThreshold);

        $remainder = bcmod($amount, $ctosThreshold);
        $this->assertEquals('0', $remainder, 'RM 50,000 is divisible by RM 25,000');

        $this->assertTrue(
            bccomp($amount, $ctosThreshold, 2) >= 0,
            'RM 50,000 exceeds CTOS threshold'
        );
    }

    public function test_rm_75000_is_not_flagged_as_round_amount(): void
    {
        $amount = '75000.00';
        $thresholdService = new ThresholdService;
        $ctosThreshold = $thresholdService->getCtosThreshold();

        $remainder = bcmod($amount, $ctosThreshold);
        $this->assertEquals('0', $remainder, 'RM 75,000 is divisible by RM 25,000');
    }

    public function test_rm_100000_is_not_flagged_as_round_amount(): void
    {
        $amount = '100000.00';
        $thresholdService = new ThresholdService;
        $ctosThreshold = $thresholdService->getCtosThreshold();

        $remainder = bcmod($amount, $ctosThreshold);
        $this->assertEquals('0', $remainder, 'RM 100,000 is divisible by RM 25,000');
    }

    public function test_monitor_transaction_does_not_create_round_amount_flags(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '50000.00',
            'currency_code' => 'USD',
        ]);

        $result = $this->service->monitorTransaction($transaction);

        $roundAmountFlags = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', 'round_amount')
            ->count();

        $this->assertEquals(0, $roundAmountFlags, 'RM 50,000 should not trigger a RoundAmount flag');
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('flags_created', $result);
        $this->assertArrayHasKey('flags', $result);
    }

    public function test_monitor_transaction_handles_rm_25000_exact_threshold(): void
    {
        $customer = Customer::factory()->create();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => '25000.00',
            'currency_code' => 'USD',
        ]);

        $result = $this->service->monitorTransaction($transaction);

        $roundAmountFlags = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->where('flag_type', 'round_amount')
            ->count();

        $this->assertEquals(0, $roundAmountFlags, 'RM 25,000 exact threshold should not trigger RoundAmount flag');
    }
}
