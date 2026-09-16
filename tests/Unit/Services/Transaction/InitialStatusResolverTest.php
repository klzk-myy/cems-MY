<?php

namespace Tests\Unit\Services\Transaction;

use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\InitialStatusResolver;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class InitialStatusResolverTest extends TestCase
{
    private MathService&MockInterface $mathService;

    private ThresholdService&MockInterface $thresholdService;

    private InitialStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = Mockery::mock(MathService::class);
        $this->thresholdService = Mockery::mock(ThresholdService::class);
        $this->resolver = new InitialStatusResolver($this->mathService, $this->thresholdService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_returns_pending_approval_when_hold_required(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('10000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('100.00', '10000.00')
            ->andReturn(-1);

        $result = $this->resolver->resolve('100.00', true, RiskRating::Low);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertTrue($result->isPending());
        $this->assertSame('Compliance hold', $result->holdReason);
    }

    public function test_uses_upstream_hold_reasons_when_provided(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->andReturn('10000.00');
        $this->mathService->shouldReceive('compare')->andReturn(-1);

        $result = $this->resolver->resolve('100.00', true, RiskRating::Low, ['EDD required', 'Sanction flag']);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertSame('EDD required; Sanction flag', $result->holdReason);
    }

    public function test_completes_medium_risk_below_threshold(): void
    {
        // Documented policy: Medium risk alone does not force approval.
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('10000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('100.00', '10000.00')
            ->andReturn(-1);

        $result = $this->resolver->resolve('100.00', false, RiskRating::Medium);

        $this->assertSame(TransactionStatus::Completed, $result->status);
        $this->assertNull($result->holdReason);
    }

    public function test_returns_pending_approval_for_high_risk(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->andReturn('10000.00');
        $this->mathService->shouldReceive('compare')->andReturn(-1);

        $result = $this->resolver->resolve('100.00', false, RiskRating::High);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertSame('Customer risk rating is High', $result->holdReason);
    }

    public function test_returns_pending_approval_for_null_risk(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->andReturn('10000.00');
        $this->mathService->shouldReceive('compare')->andReturn(-1);

        $result = $this->resolver->resolve('100.00', false, null);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertSame('Customer risk rating is unknown', $result->holdReason);
    }

    public function test_returns_pending_approval_when_amount_meets_threshold(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('3000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('3000.00', '3000.00')
            ->andReturn(0);

        $result = $this->resolver->resolve('3000.00', false, RiskRating::Low);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertSame('Transaction amount exceeds auto-approve threshold', $result->holdReason);
    }

    public function test_returns_completed_when_below_threshold(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('3000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('2999.99', '3000.00')
            ->andReturn(-1);

        $result = $this->resolver->resolve('2999.99', false, RiskRating::Low);

        $this->assertSame(TransactionStatus::Completed, $result->status);
        $this->assertNull($result->holdReason);
        $this->assertSame([], $result->reasons);
    }

    public function test_accumulates_multiple_reasons(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('1000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('5000.00', '1000.00')
            ->andReturn(1);

        $result = $this->resolver->resolve('5000.00', true, RiskRating::High, ['EDD required']);

        $this->assertSame(TransactionStatus::PendingApproval, $result->status);
        $this->assertSame(
            'EDD required; Customer risk rating is High; Transaction amount exceeds auto-approve threshold',
            $result->holdReason
        );
    }
}
