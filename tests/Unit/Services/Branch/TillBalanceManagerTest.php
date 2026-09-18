<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TillBalanceManagerTest extends TestCase
{
    use RefreshDatabase;

    private TillBalanceManager $service;

    private Branch $branch;

    private Counter $counter;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->counter = Counter::factory()->for($this->branch, 'branch')->create();
        $this->user = User::factory()->for($this->branch, 'branch')->create(['role' => UserRole::Teller]);

        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now(),
            'opened_by' => $this->user->id,
            'status' => CounterSessionStatus::Open,
        ]);

        TellerAllocation::create([
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'allocated_amount' => '1000.00',
            'current_balance' => '1000.00',
            'requested_amount' => '1000.00',
            'daily_limit_myr' => '500000.00',
            'daily_used_myr' => '0.00',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        $this->service = app(TillBalanceManager::class);
    }

    #[Test]
    public function open_balance_creates_till_balance(): void
    {
        $before = TillBalance::count();

        $balance = $this->service->openBalance($this->counter, 'USD', $this->user->id);

        $this->assertInstanceOf(TillBalance::class, $balance);
        $this->assertSame($before + 1, TillBalance::count());
    }

    #[Test]
    public function variance_returns_zero_when_closing_balance_is_null(): void
    {
        $balance = TillBalance::create([
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'USD',
            'branch_id' => $this->branch->id,
            'opening_balance' => '1000.00',
            'date' => now()->toDateString(),
            'opened_by' => $this->user->id,
        ]);

        $this->assertEquals('0', $this->service->variance($balance));
    }
}
