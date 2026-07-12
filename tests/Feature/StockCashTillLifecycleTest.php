<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockCashTillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manager_can_open_till_via_stock_cash_route(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $counter = Counter::factory()->create();
        $currency = Currency::factory()->create();

        $this->actingAs($manager)
            ->post(route('stock-cash.open'), [
                'till_id' => $counter->code,
                'currency_code' => $currency->code,
                'opening_balance' => 1000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('till_balances', [
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'opening_balance' => '1000',
        ]);
    }

    #[Test]
    public function manager_can_close_till_via_stock_cash_route(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $counter = Counter::factory()->create();
        $currency = Currency::factory()->create();
        TillBalance::factory()->create([
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'date' => today(),
            'opening_balance' => '1000',
            'closed_at' => null,
        ]);

        $this->actingAs($manager)
            ->post(route('stock-cash.close'), [
                'till_id' => $counter->code,
                'currency_code' => $currency->code,
                'closing_balance' => 1000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('till_balances', [
            'till_id' => $counter->code,
            'currency_code' => $currency->code,
            'closing_balance' => '1000',
        ]);
    }
}
