<?php

namespace Tests\Unit\Services\Traits;

use App\Enums\TransactionType;
use App\Models\TillBalance;
use App\Services\Branch\TillBalanceManager;
use App\Services\Traits\TillBalanceTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TillBalanceTraitTest extends TestCase
{
    use TillBalanceTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tillBalanceManager = $this->createMock(TillBalanceManager::class);
    }

    public function test_update_till_balance_calls_manager(): void
    {
        /** @var TillBalanceManager&MockObject $mockManager */
        $mockManager = $this->tillBalanceManager;

        $mockManager->expects($this->once())
            ->method('applyTransaction')
            ->with(
                $this->isInstanceOf(TillBalance::class),
                $this->equalTo(TransactionType::Buy),
                $this->equalTo('1000.00'),
                $this->equalTo('250.00')
            );

        $tillBalance = new TillBalance;
        $this->updateTillBalance($tillBalance, 'Buy', '1000.00', '250.00');
    }

    public function test_update_till_balance_with_sell_type(): void
    {
        /** @var TillBalanceManager&MockObject $mockManager */
        $mockManager = $this->tillBalanceManager;

        $mockManager->expects($this->once())
            ->method('applyTransaction')
            ->with(
                $this->isInstanceOf(TillBalance::class),
                $this->equalTo(TransactionType::Sell),
                $this->equalTo('500.00'),
                $this->equalTo('125.00')
            );

        $tillBalance = new TillBalance;
        $this->updateTillBalance($tillBalance, 'Sell', '500.00', '125.00');
    }
}
