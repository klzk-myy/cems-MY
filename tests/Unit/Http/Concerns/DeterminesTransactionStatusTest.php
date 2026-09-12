<?php

namespace Tests\Unit\Http\Concerns;

use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Http\Concerns\DeterminesTransactionStatus;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\DTOs\AllocationValidationResult;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeterminesTransactionStatusTest extends TestCase
{
    use DeterminesTransactionStatus;

    private MathService&MockInterface $mathService;

    private TellerAllocationService&MockInterface $tellerAllocationService;

    private ThresholdService&MockInterface $thresholdService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = Mockery::mock(MathService::class);
        $this->tellerAllocationService = Mockery::mock(TellerAllocationService::class);
        $this->thresholdService = Mockery::mock(ThresholdService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_determine_teller_allocation_returns_null_for_non_teller(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(false);

        $allocation = $this->determineTellerAllocation($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');

        $this->assertNull($allocation);
    }

    public function test_determine_teller_allocation_buy_returns_valid_allocation(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $expectedAllocation = Mockery::mock(TellerAllocation::class);

        $this->tellerAllocationService->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: true,
                allocation: $expectedAllocation
            ));

        $allocation = $this->determineTellerAllocation($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');

        $this->assertSame($expectedAllocation, $allocation);
    }

    public function test_determine_teller_allocation_buy_throws_when_validation_fails(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $this->tellerAllocationService->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: false,
                reason: 'Insufficient allocation balance'
            ));

        $this->expectException(AllocationValidationException::class);
        $this->expectExceptionMessage('Allocation validation failed: Insufficient allocation balance');

        $this->determineTellerAllocation($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');
    }

    public function test_determine_teller_allocation_sell_returns_active_allocation(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $expectedAllocation = Mockery::mock(TellerAllocation::class);

        $this->tellerAllocationService->shouldReceive('validateTransaction')->never();
        $this->tellerAllocationService->shouldReceive('getActiveAllocation')
            ->once()
            ->with($user, 'EUR')
            ->andReturn($expectedAllocation);

        $allocation = $this->determineTellerAllocation($user, [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'EUR',
        ], '500.00');

        $this->assertSame($expectedAllocation, $allocation);
    }

    public function test_determine_initial_status_returns_pending_approval_when_hold_required(): void
    {
        $this->mathService->shouldReceive('compare')->never();
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->never();

        $status = $this->determineInitialStatus('100.00', true, RiskRating::Low);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_completes_medium_risk_below_threshold(): void
    {
        // Only High risk (or a hold) blocks auto-completion — Medium risk
        // below the auto-approve threshold completes.
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('10000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('100.00', '10000.00')
            ->andReturn(-1);

        $status = $this->determineInitialStatus('100.00', false, RiskRating::Medium);

        $this->assertSame(TransactionStatus::Completed, $status);
    }

    public function test_determine_initial_status_returns_pending_approval_for_high_risk(): void
    {
        $this->mathService->shouldReceive('compare')->never();
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->never();

        $status = $this->determineInitialStatus('100.00', false, RiskRating::High);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_returns_pending_approval_for_null_risk(): void
    {
        $this->mathService->shouldReceive('compare')->never();
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')->never();

        $status = $this->determineInitialStatus('100.00', false, null);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_returns_pending_approval_when_amount_meets_threshold(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('3000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('3000.00', '3000.00')
            ->andReturn(0);

        $status = $this->determineInitialStatus('3000.00', false, RiskRating::Low);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_returns_completed_when_below_threshold(): void
    {
        $this->thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('3000.00');

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('2999.99', '3000.00')
            ->andReturn(-1);

        $status = $this->determineInitialStatus('2999.99', false, RiskRating::Low);

        $this->assertSame(TransactionStatus::Completed, $status);
    }
}
