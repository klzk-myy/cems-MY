<?php

namespace Tests\Unit\Listeners;

use App\Enums\RecalculationTrigger;
use App\Events\TransactionCreated;
use App\Listeners\TransactionCreatedListener;
use App\Models\Transaction;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\Transaction\TransactionMonitoringService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreatedListenerTest extends TestCase
{
    #[Test]
    public function it_persists_the_risk_recalculation_instead_of_discarding_it(): void
    {
        $transaction = new Transaction(['customer_id' => 42]);

        $monitoring = $this->createMock(TransactionMonitoringService::class);
        $monitoring->expects($this->once())
            ->method('monitorTransaction')
            ->with($transaction);

        $engine = $this->createMock(RiskScoringEngine::class);
        $engine->expects($this->once())
            ->method('recalculate')
            ->with(42, RecalculationTrigger::EventDriven);
        $engine->expects($this->never())->method('calculateScore');

        (new TransactionCreatedListener($monitoring, $engine))
            ->handle(new TransactionCreated($transaction));
    }
}
