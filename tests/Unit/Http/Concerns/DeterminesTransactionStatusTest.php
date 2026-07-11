<?php

namespace Tests\Unit\Http\Concerns;

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
use Tests\TestCase;

class DeterminesTransactionStatusTest extends TestCase
{
    use DeterminesTransactionStatus;

    private MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = Mockery::mock(MathService::class);
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

        $service = Mockery::mock(TellerAllocationService::class);
        $service->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: true,
                allocation: $expectedAllocation
            ));

        $this->app->instance(TellerAllocationService::class, $service);

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

        $service = Mockery::mock(TellerAllocationService::class);
        $service->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: false,
                reason: 'Insufficient allocation balance'
            ));

        $this->app->instance(TellerAllocationService::class, $service);

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

        $service = Mockery::mock(TellerAllocationService::class);
        $service->shouldReceive('validateTransaction')->never();
        $service->shouldReceive('getActiveAllocation')
            ->once()
            ->with($user, 'EUR')
            ->andReturn($expectedAllocation);

        $this->app->instance(TellerAllocationService::class, $service);

        $allocation = $this->determineTellerAllocation($user, [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'EUR',
        ], '500.00');

        $this->assertSame($expectedAllocation, $allocation);
    }

    public function test_determine_initial_status_returns_pending_approval_when_hold_required(): void
    {
        $this->mathService->shouldReceive('compare')->never();

        $status = $this->determineInitialStatus('100.00', true);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_returns_pending_approval_when_amount_meets_threshold(): void
    {
        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('10000.00');
        $this->app->instance(ThresholdService::class, $thresholdService);

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('10000.00', '10000.00')
            ->andReturn(0);

        $status = $this->determineInitialStatus('10000.00', false);

        $this->assertSame(TransactionStatus::PendingApproval, $status);
    }

    public function test_determine_initial_status_returns_completed_when_below_threshold(): void
    {
        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getAutoApproveThreshold')
            ->once()
            ->andReturn('10000.00');
        $this->app->instance(ThresholdService::class, $thresholdService);

        $this->mathService->shouldReceive('compare')
            ->once()
            ->with('9999.99', '10000.00')
            ->andReturn(-1);

        $status = $this->determineInitialStatus('9999.99', false);

        $this->assertSame(TransactionStatus::Completed, $status);
    }
}
