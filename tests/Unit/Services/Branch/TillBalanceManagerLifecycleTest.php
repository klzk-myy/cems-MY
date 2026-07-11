<?php

namespace Tests\Unit\Services\Branch;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use App\Services\Branch\TillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TillBalanceManagerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected TillBalanceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(TillBalanceManager::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function open_till_creates_till_balance_with_opening_balance(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openTill($till, $currency->code, '1500.50', $user->id, 'Morning float');

        $this->assertNotNull($balance);
        $this->assertEquals($till->code, $balance->till_id);
        $this->assertEquals($currency->code, $balance->currency_code);
        $this->assertEquals('1500.5000', (string) $balance->opening_balance);
        $this->assertEquals($till->branch_id, $balance->branch_id);
        $this->assertEquals($user->id, $balance->opened_by);
        $this->assertEquals('Morning float', $balance->notes);
        $this->assertNull($balance->closing_balance);
        $this->assertNull($balance->closed_at);
    }

    #[Test]
    public function open_till_throws_when_balance_already_exists_for_today(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $this->manager->openTill($till, $currency->code, '1000.00', $user->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Till already opened for this currency today.');

        $this->manager->openTill($till, $currency->code, '2000.00', $user->id);
    }

    #[Test]
    public function close_till_calculates_variance_using_till_service_calculate_net_flow(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();
        $closer = User::factory()->create();

        $balance = TillBalance::factory()->create([
            'till_id' => $till->code,
            'currency_code' => $currency->code,
            'opening_balance' => '1000.00',
            'opened_by' => $user->id,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $tillService = Mockery::mock(TillService::class);
        $tillService->shouldReceive('calculateNetFlow')
            ->once()
            ->with($till->code, $currency->code)
            ->andReturn('250.00');

        $this->app->instance(TillService::class, $tillService);
        $manager = app(TillBalanceManager::class);

        $updated = $manager->closeTill($balance, '1250.00', $closer->id, 'End of day');

        $this->assertEquals('1250.0000', (string) $updated->closing_balance);
        $this->assertEquals('0.0000', (string) $updated->variance);
        $this->assertEquals($closer->id, $updated->closed_by);
        $this->assertNotNull($updated->closed_at);
        $this->assertEquals('End of day', $updated->notes);
    }

    #[Test]
    public function close_till_throws_when_till_already_closed(): void
    {
        $balance = TillBalance::factory()->create([
            'closing_balance' => '1000.00',
            'closed_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Till already closed for today.');

        $this->manager->closeTill($balance, '1100.00');
    }

    #[Test]
    public function close_till_updates_closed_at_closed_by_and_closing_balance(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();
        $closer = User::factory()->create();

        $balance = TillBalance::factory()->create([
            'till_id' => $till->code,
            'currency_code' => $currency->code,
            'opening_balance' => '500.00',
            'opened_by' => $user->id,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $tillService = Mockery::mock(TillService::class);
        $tillService->shouldReceive('calculateNetFlow')
            ->andReturn('100.00');

        $this->app->instance(TillService::class, $tillService);
        $manager = app(TillBalanceManager::class);

        $updated = $manager->closeTill($balance, '650.00', $closer->id);

        $this->assertEquals('650.0000', (string) $updated->closing_balance);
        $this->assertEquals('50.0000', (string) $updated->variance);
        $this->assertEquals($closer->id, $updated->closed_by);
        $this->assertNotNull($updated->closed_at);
    }
}
