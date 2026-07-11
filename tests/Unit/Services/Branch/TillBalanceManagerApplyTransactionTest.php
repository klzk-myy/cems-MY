<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\TransactionType;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TillBalanceManagerApplyTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected TillBalanceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(TillBalanceManager::class);
    }

    private function openBalances(Counter $till, Currency $foreignCurrency, ?User $user = null): array
    {
        $user ??= User::factory()->create();

        return [
            $this->manager->openBalance($till, $foreignCurrency->code, $user->id),
            $this->manager->openBalance($till, 'MYR', $user->id),
        ];
    }

    #[Test]
    public function apply_buy_transaction_increments_foreign_and_decrements_myr(): void
    {
        $till = Counter::factory()->create();
        $foreignCurrency = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'MYR']);

        [$foreignBalance, $myrBalance] = $this->openBalances($till, $foreignCurrency);

        $this->manager->applyTransaction(
            $foreignBalance,
            TransactionType::Buy,
            '150.00',
            '100.00'
        );

        $this->assertEquals('100.0000', (string) $foreignBalance->fresh()->foreign_total);
        $this->assertEquals('100.0000', (string) $foreignBalance->fresh()->buy_total_foreign);
        $this->assertEquals('-150.0000', (string) $myrBalance->fresh()->transaction_total);
    }

    #[Test]
    public function apply_sell_transaction_decrements_foreign_and_increments_myr(): void
    {
        $till = Counter::factory()->create();
        $foreignCurrency = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'MYR']);

        [$foreignBalance, $myrBalance] = $this->openBalances($till, $foreignCurrency);

        // Pre-seed foreign stock so the sell can subtract from it
        $this->manager->adjustBalance($foreignBalance, 'foreign_total', '200.00', 'add');

        $this->manager->applyTransaction(
            $foreignBalance,
            TransactionType::Sell,
            '300.00',
            '100.00'
        );

        $this->assertEquals('100.0000', (string) $foreignBalance->fresh()->foreign_total);
        $this->assertEquals('100.0000', (string) $foreignBalance->fresh()->sell_total_foreign);
        $this->assertEquals('300.0000', (string) $myrBalance->fresh()->transaction_total);
    }

    #[Test]
    public function reverse_buy_transaction_inverts_apply_buy(): void
    {
        $till = Counter::factory()->create();
        $foreignCurrency = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'MYR']);

        [$foreignBalance, $myrBalance] = $this->openBalances($till, $foreignCurrency);

        $this->manager->applyTransaction($foreignBalance, TransactionType::Buy, '150.00', '100.00');
        $this->manager->reverseTransaction($foreignBalance, TransactionType::Buy, '150.00', '100.00');

        $this->assertEquals('0.0000', (string) $foreignBalance->fresh()->foreign_total);
        $this->assertEquals('0.0000', (string) $foreignBalance->fresh()->buy_total_foreign);
        $this->assertEquals('0.0000', (string) $myrBalance->fresh()->transaction_total);
    }

    #[Test]
    public function reverse_sell_transaction_inverts_apply_sell(): void
    {
        $till = Counter::factory()->create();
        $foreignCurrency = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'MYR']);

        [$foreignBalance, $myrBalance] = $this->openBalances($till, $foreignCurrency);

        $this->manager->adjustBalance($foreignBalance, 'foreign_total', '200.00', 'add');
        $this->manager->applyTransaction($foreignBalance, TransactionType::Sell, '300.00', '100.00');
        $this->manager->reverseTransaction($foreignBalance, TransactionType::Sell, '300.00', '100.00');

        $this->assertEquals('200.0000', (string) $foreignBalance->fresh()->foreign_total);
        $this->assertEquals('0.0000', (string) $foreignBalance->fresh()->sell_total_foreign);
        $this->assertEquals('0.0000', (string) $myrBalance->fresh()->transaction_total);
    }

    #[Test]
    public function apply_transaction_throws_when_myr_balance_missing(): void
    {
        $till = Counter::factory()->create();
        $foreignCurrency = Currency::factory()->create(['code' => 'USD']);
        $user = User::factory()->create();

        $foreignBalance = $this->manager->openBalance($till, $foreignCurrency->code, $user->id);

        $this->expectException(TillBalanceMissingException::class);
        $this->manager->applyTransaction($foreignBalance, TransactionType::Buy, '150.00', '100.00');
    }

    #[Test]
    public function reverse_transaction_logs_warning_when_counter_missing(): void
    {
        $foreignBalance = TillBalance::factory()->create([
            'till_id' => 'UNKNOWN',
            'currency_code' => 'USD',
        ]);

        $this->manager->reverseTransaction($foreignBalance, TransactionType::Buy, '150.00', '100.00');

        $this->assertEquals('0.0000', (string) $foreignBalance->fresh()->foreign_total);
    }
}
