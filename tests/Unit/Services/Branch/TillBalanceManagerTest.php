<?php

namespace Tests\Unit\Services\Branch;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TillBalanceManagerTest extends TestCase
{
    use RefreshDatabase;

    protected TillBalanceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(TillBalanceManager::class);
    }

    #[Test]
    public function opens_till_balance_for_currency(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openBalance($till, $currency->code, $user->id);

        $this->assertNotNull($balance);
        $this->assertEquals($currency->code, $balance->currency_code);
        $this->assertEquals($till->code, $balance->till_id);
    }

    #[Test]
    public function throws_when_currency_not_found(): void
    {
        $till = Counter::factory()->create();
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $this->manager->openBalance($till, 'XYZ', $user->id);
    }

    #[Test]
    public function adjust_balance_adds_to_field(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openBalance($till, $currency->code, $user->id);
        $updated = $this->manager->adjustBalance($balance, 'foreign_total', '100.50', 'add');

        $this->assertEquals('100.5000', (string) $updated->foreign_total);
    }

    #[Test]
    public function adjust_balance_subtracts_from_field(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openBalance($till, $currency->code, $user->id);
        $this->manager->adjustBalance($balance, 'foreign_total', '200.00', 'add');
        $updated = $this->manager->adjustBalance($balance, 'foreign_total', '75.00', 'subtract');

        $this->assertEquals('125.0000', (string) $updated->foreign_total);
    }

    #[Test]
    public function adjust_balance_throws_for_invalid_operation(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openBalance($till, $currency->code, $user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->adjustBalance($balance, 'foreign_total', '100.00', 'multiply');
    }

    #[Test]
    public function adjust_balance_throws_for_invalid_field(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $balance = $this->manager->openBalance($till, $currency->code, $user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->adjustBalance($balance, 'invalid_field', '100.00', 'add');
    }

    #[Test]
    public function current_balance_returns_open_balance(): void
    {
        $till = Counter::factory()->create();
        $currency = Currency::factory()->create();
        $user = User::factory()->create();

        $this->manager->openBalance($till, $currency->code, $user->id);
        $balance = $this->manager->currentBalance($till, $currency->code);

        $this->assertNotNull($balance);
        $this->assertEquals($currency->code, $balance->currency_code);
    }

    #[Test]
    public function current_balance_returns_null_when_missing(): void
    {
        $till = Counter::factory()->create();

        $balance = $this->manager->currentBalance($till, 'USD');

        $this->assertNull($balance);
    }

    #[Test]
    public function variance_returns_calculated_variance(): void
    {
        $balance = TillBalance::factory()->create([
            'opening_balance' => '1000.00',
            'closing_balance' => '1200.00',
            'buy_total_foreign' => '500.00',
            'sell_total_foreign' => '200.00',
        ]);

        // Expected = opening + buy - sell = 1000 + 500 - 200 = 1300
        // Variance = closing - expected = 1200 - 1300 = -100
        $this->assertEquals('-100.0000', $this->manager->variance($balance));
    }
}
