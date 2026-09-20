<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Exceptions\Domain\MissingClosingFloatException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\CounterService;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 6 regressions (C1/C2): till-close variance must come from
 * TillBalance::getExpectedBalance() — the same formula closeSession and
 * reconciliation use — and a session close must count every open currency.
 */
class TillCloseVarianceTest extends TestCase
{
    use RefreshDatabase;

    protected Counter $counter;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counter = Counter::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_close_till_fcy_variance_uses_foreign_units(): void
    {
        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        // USD drawer: opened with 1000 USD, bought 300, sold 50.
        // Expected = 1250 USD — foreign units, not MYR.
        /** @var TillBalance $balance */
        $balance = TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => 'USD',
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '1000.00',
            'buy_quantity' => '300.00',
            'sell_quantity' => '50.00',
        ]);

        // A huge MYR-denominated transaction must not leak into the FCY
        // drawer expectation (the old calculateNetFlow summed amount_myr
        // onto every currency row).
        $closed = app(TillBalanceManager::class)->closeTill($balance, '1250.00', $this->user->id);

        $this->assertEquals('1250.0000', (string) $closed->closing_balance);
        $this->assertEquals('0.0000', (string) $closed->variance);
    }

    #[Test]
    public function test_close_till_myr_variance_reflects_day_movement(): void
    {
        // MYR drawer: opened with 10000, net movement tracked in
        // transaction_total_myr (-2300 of sells minus buys settled in MYR).
        /** @var TillBalance $balance */
        $balance = TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => Currency::baseCurrency(),
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '10000.00',
            'transaction_total_myr' => '-2300.00',
        ]);

        // Expected = 10000 - 2300 = 7700; counted 7700 -> zero variance.
        $closed = app(TillBalanceManager::class)->closeTill($balance, '7700.00', $this->user->id);
        $this->assertEquals('0.0000', (string) $closed->variance);
    }

    #[Test]
    public function test_close_session_rejects_uncounted_currency(): void
    {
        Currency::firstOrCreate(['code' => Currency::baseCurrency()], [
            'name' => 'Malaysian Ringgit',
            'symbol' => 'RM',
            'decimal_places' => 2,
        ]);
        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'opened_by' => $this->user->id,
            'status' => CounterSessionStatus::Open->value,
        ]);

        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => Currency::baseCurrency(),
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '1000.00',
        ]);
        $usdBalance = TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => 'USD',
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '500.00',
        ]);

        // Only the MYR drawer is counted — the USD row would previously be
        // orphaned open forever.
        try {
            app(CounterService::class)->closeSession($session, $this->user, [
                ['currency_id' => Currency::baseCurrency(), 'quantity' => '1000.00'],
            ]);
            $this->fail('Expected MissingClosingFloatException');
        } catch (MissingClosingFloatException $e) {
            $this->assertContains('USD', $e->currencyCodes);
        }

        // Nothing closed: session and both till rows remain open.
        $this->assertTrue($session->fresh()->isOpen());
        /** @var TillBalance $usdFresh */
        $usdFresh = $usdBalance->fresh();
        $this->assertNull($usdFresh->closed_at);
    }

    #[Test]
    public function test_close_session_closes_all_counted_currencies(): void
    {
        Currency::firstOrCreate(['code' => Currency::baseCurrency()], [
            'name' => 'Malaysian Ringgit',
            'symbol' => 'RM',
            'decimal_places' => 2,
        ]);
        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'opened_by' => $this->user->id,
            'status' => CounterSessionStatus::Open->value,
        ]);

        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => Currency::baseCurrency(),
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '1000.00',
        ]);
        TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => 'USD',
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '500.00',
        ]);

        $closed = app(CounterService::class)->closeSession($session, $this->user, [
            ['currency_id' => Currency::baseCurrency(), 'quantity' => '1000.00'],
            ['currency_id' => 'USD', 'quantity' => '500.00'],
        ]);

        $this->assertTrue($closed->fresh()->status === CounterSessionStatus::Closed);
        $this->assertSame(
            0,
            TillBalance::where('till_id', $this->counter->code)
                ->whereNull('closed_at')
                ->count(),
            'No open till rows may survive a session close'
        );
    }
}
