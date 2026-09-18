<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\DTOs\AllocationValidationResult;
use App\Services\System\MathService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TellerAllocationResolutionTest extends TestCase
{
    private TellerAllocationService&MockInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Mockery::mock(TellerAllocationService::class, [
            Mockery::mock(BranchPoolService::class),
            Mockery::mock(MathService::class),
            Mockery::mock(AuditService::class),
            Mockery::mock(TillService::class),
        ])->makePartial();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_resolve_for_transaction_returns_null_for_non_teller(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(false);

        $allocation = $this->service->resolveForTransaction($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');

        $this->assertNull($allocation);
    }

    public function test_resolve_for_transaction_buy_returns_valid_allocation(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $expectedAllocation = Mockery::mock(TellerAllocation::class);

        $this->service->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: true,
                allocation: $expectedAllocation
            ));

        $allocation = $this->service->resolveForTransaction($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');

        $this->assertSame($expectedAllocation, $allocation);
    }

    public function test_resolve_for_transaction_buy_throws_when_validation_fails(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $this->service->shouldReceive('validateTransaction')
            ->once()
            ->with($user, 'USD', '1000.00', true)
            ->andReturn(new AllocationValidationResult(
                valid: false,
                reason: 'Insufficient allocation balance'
            ));

        $this->expectException(AllocationValidationException::class);
        $this->expectExceptionMessage('Allocation validation failed: Insufficient allocation balance');

        $this->service->resolveForTransaction($user, [
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
        ], '1000.00');
    }

    public function test_resolve_for_transaction_sell_returns_active_allocation(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isTeller')->once()->andReturn(true);

        $expectedAllocation = Mockery::mock(TellerAllocation::class);

        $this->service->shouldReceive('validateTransaction')->never();
        $this->service->shouldReceive('getActiveAllocation')
            ->once()
            ->with($user, 'EUR')
            ->andReturn($expectedAllocation);

        $allocation = $this->service->resolveForTransaction($user, [
            'type' => TransactionType::Sell->value,
            'currency_code' => 'EUR',
        ], '500.00');

        $this->assertSame($expectedAllocation, $allocation);
    }
}
