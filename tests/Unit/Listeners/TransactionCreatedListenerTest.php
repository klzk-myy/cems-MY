<?php

namespace Tests\Unit\Listeners;

use App\Enums\RecalculationTrigger;
use App\Events\TransactionCreated;
use App\Listeners\TransactionCreatedListener;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\System\CacheInvalidationService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreatedListenerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_the_risk_recalculation_instead_of_discarding_it(): void
    {
        $transaction = new Transaction(['customer_id' => 42]);

        $monitoring = $this->createMock(TransactionMonitoringService::class);
        $monitoring->expects($this->once())
            ->method('monitorTransaction')
            ->with($transaction);

        $cache = $this->createMock(CacheInvalidationService::class);
        $cache->expects($this->once())
            ->method('forgetReportData');

        $engine = $this->createMock(RiskScoringEngine::class);
        $engine->expects($this->once())
            ->method('recalculate')
            ->with(42, RecalculationTrigger::EventDriven);
        $engine->expects($this->never())->method('calculateScore');

        (new TransactionCreatedListener($monitoring, $engine, $cache))
            ->handle(new TransactionCreated($transaction));
    }

    #[Test]
    public function it_never_moves_last_transaction_at_backwards(): void
    {
        $customer = Customer::factory()->create([
            'last_transaction_at' => now()->subHours(2),
        ]);

        $newer = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(2),
        ]);
        $older = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'created_at' => now()->subHours(4),
        ]);

        $monitoring = $this->createMock(TransactionMonitoringService::class);
        $monitoring->expects($this->exactly(2))->method('monitorTransaction');

        $cache = $this->createMock(CacheInvalidationService::class);
        $cache->method('forgetReportData');

        $engine = $this->createMock(RiskScoringEngine::class);

        $listener = new TransactionCreatedListener($monitoring, $engine, $cache);

        // Out-of-order dispatch: the older event arrives after the newer one
        // and must not regress the timestamp.
        $listener->handle(new TransactionCreated($older));

        $this->assertSame(
            now()->subHours(2)->toDateTimeString(),
            $customer->refresh()->last_transaction_at->toDateTimeString()
        );

        $listener->handle(new TransactionCreated($newer));

        $this->assertSame(
            now()->subHours(2)->toDateTimeString(),
            $customer->refresh()->last_transaction_at->toDateTimeString()
        );
    }
}
