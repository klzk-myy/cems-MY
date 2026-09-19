<?php

namespace Tests\Unit\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;
use App\Services\DTOs\ComplianceCheckResult;
use App\Services\Transaction\Checks\AggregateTransactionsCheck;
use App\Services\Transaction\Checks\DurationOnHoldCheck;
use App\Services\Transaction\Checks\HighRiskCountryCheck;
use App\Services\Transaction\Checks\HoldReasonCheck;
use App\Services\Transaction\Checks\ProfileDeviationCheck;
use App\Services\Transaction\Checks\StructuringCheck;
use App\Services\Transaction\Checks\TransactionCheckRegistry;
use App\Services\Transaction\Checks\UnusualPatternCheck;
use App\Services\Transaction\Checks\VelocityCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCheckTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registry_returns_checks_in_monitoring_order(): void
    {
        $registry = app(TransactionCheckRegistry::class);

        $this->assertSame([
            VelocityCheck::class,
            StructuringCheck::class,
            AggregateTransactionsCheck::class,
            UnusualPatternCheck::class,
            HighRiskCountryCheck::class,
            ProfileDeviationCheck::class,
            DurationOnHoldCheck::class,
            HoldReasonCheck::class,
        ], array_map('get_class', $registry->checks()));
    }

    #[Test]
    public function velocity_check_flags_when_threshold_exceeded(): void
    {
        $compliance = Mockery::mock(ComplianceService::class);
        $compliance->shouldReceive('checkVelocity')->once()->andReturn([
            'threshold_exceeded' => true,
            'with_new_transaction' => '150000.0000',
        ]);

        $check = new VelocityCheck($compliance);
        $transaction = Transaction::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'amount_myr' => '50000.0000',
        ]);

        $descriptors = $check->check($transaction);

        $this->assertCount(1, $descriptors);
        $this->assertSame(ComplianceFlagType::Velocity, $descriptors[0]->type);
        $this->assertStringContainsString('150000.0000', $descriptors[0]->reason);
        $this->assertSame('aml_velocity_alert_triggered', $descriptors[0]->auditEvent);
        $this->assertSame(1, $descriptors[0]->auditPayload['transaction_count']);
    }

    #[Test]
    public function velocity_check_returns_nothing_below_threshold(): void
    {
        $compliance = Mockery::mock(ComplianceService::class);
        $compliance->shouldReceive('checkVelocity')->once()->andReturn([
            'threshold_exceeded' => false,
            'with_new_transaction' => '100.0000',
        ]);

        $check = new VelocityCheck($compliance);
        $transaction = new Transaction;
        $transaction->customer_id = 1;
        $transaction->amount_myr = '100.0000';

        $this->assertSame([], $check->check($transaction));
    }

    #[Test]
    public function hold_reason_check_emits_one_descriptor_per_reason(): void
    {
        $compliance = Mockery::mock(ComplianceService::class);
        $compliance->shouldReceive('requiresHold')->once()->andReturn(
            new ComplianceCheckResult(true, ['reason A', 'reason B'])
        );

        $check = new HoldReasonCheck($compliance);
        $transaction = new Transaction;
        $transaction->status = TransactionStatus::Completed;
        $transaction->approved_by = null;
        $transaction->amount_myr = '50000.0000';
        $transaction->setRelation('customer', new Customer);

        $descriptors = $check->check($transaction);

        $this->assertCount(2, $descriptors);
        $this->assertSame('reason A', $descriptors[0]->reason);
        $this->assertSame('reason B', $descriptors[1]->reason);
        $this->assertSame(ComplianceFlagType::EddRequired, $descriptors[0]->type);
    }

    #[Test]
    public function hold_reason_check_skips_non_completed_transactions(): void
    {
        $compliance = Mockery::mock(ComplianceService::class);
        $compliance->shouldReceive('requiresHold')->andReturn(
            new ComplianceCheckResult(true, ['reason'])
        );

        $check = new HoldReasonCheck($compliance);
        $transaction = new Transaction;
        $transaction->status = TransactionStatus::PendingApproval;
        $transaction->approved_by = null;
        $transaction->amount_myr = '50000.0000';
        $transaction->setRelation('customer', new Customer);

        $this->assertSame([], $check->check($transaction));
    }

    #[Test]
    public function hold_reason_check_skips_approved_transactions(): void
    {
        $compliance = Mockery::mock(ComplianceService::class);
        $compliance->shouldReceive('requiresHold')->andReturn(
            new ComplianceCheckResult(true, ['reason'])
        );

        $check = new HoldReasonCheck($compliance);
        $transaction = new Transaction;
        $transaction->status = TransactionStatus::Completed;
        $transaction->approved_by = 5;
        $transaction->amount_myr = '50000.0000';
        $transaction->setRelation('customer', new Customer);

        $this->assertSame([], $check->check($transaction));
    }
}
