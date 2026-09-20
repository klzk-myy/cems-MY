<?php

namespace Tests\Feature\Concurrency;

use App\Enums\CounterSessionStatus;
use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\TillClosedException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\CounterService;
use App\Services\Branch\TillBalanceManager;
use PHPUnit\Framework\Attributes\Test;

/**
 * Phase 6 (C3): a second close attempt on a stale model instance must hit
 * the locked re-check inside the transaction and throw, instead of
 * overwriting the committed close.
 *
 * SQLite has no row locks, so the race is simulated with stale instances —
 * see ConcurrentTestCase.
 */
class TillCloseRaceTest extends ConcurrentTestCase
{
    protected Counter $counter;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(['code' => Currency::baseCurrency()], [
            'name' => 'Malaysian Ringgit',
            'symbol' => 'RM',
            'decimal_places' => 2,
        ]);

        $this->counter = Counter::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function second_session_close_on_stale_instance_throws(): void
    {
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

        // Path B's view of the session, taken before path A commits.
        $stale = $this->staleCopy($session);

        // Path A: closes the session.
        app(CounterService::class)->closeSession($session, $this->user, [
            ['currency_id' => Currency::baseCurrency(), 'quantity' => '1000.00'],
        ]);
        $this->assertSame(CounterSessionStatus::Closed, $session->fresh()->status);

        // Path B: stale instance still reports open — the locked re-check
        // inside the transaction must reject it.
        $this->expectException(SessionClosedException::class);
        app(CounterService::class)->closeSession($stale, $this->user, [
            ['currency_id' => Currency::baseCurrency(), 'quantity' => '1000.00'],
        ]);
    }

    #[Test]
    public function second_till_close_on_stale_instance_throws(): void
    {
        /** @var TillBalance $balance */
        $balance = TillBalance::factory()->create([
            'till_id' => $this->counter->code,
            'currency_code' => Currency::baseCurrency(),
            'branch_id' => $this->counter->branch_id,
            'opening_balance' => '1000.00',
        ]);

        $stale = $this->staleCopy($balance);

        // Path A: closes the till.
        $closed = app(TillBalanceManager::class)->closeTill($balance, '1000.00', $this->user->id);
        $this->assertNotNull($closed->closed_at);

        // Path B: stale copy still shows open — must throw on the locked row.
        $this->expectException(TillClosedException::class);
        app(TillBalanceManager::class)->closeTill($stale, '1100.00', $this->user->id);
    }
}
