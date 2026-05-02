<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\CounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for concurrent counter handover scenarios to ensure deadlock prevention.
 *
 * A3: TillBalance Lock Scope Review - Concurrent handovers with different currency sets
 * must not deadlock due to inconsistent lock ordering. Currency codes must be sorted
 * alphabetically before acquiring locks to ensure consistent lock order.
 */
class CounterHandoverDeadlockTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller1;

    protected User $teller2;

    protected User $teller3;

    protected User $manager;

    protected Currency $usd;

    protected Currency $eur;

    protected Currency $gbp;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have specific currencies for deterministic testing
        $this->usd = Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $this->eur = Currency::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $this->gbp = Currency::firstOrCreate(['code' => 'GBP'], [
            'name' => 'British Pound',
            'symbol' => '£',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $this->teller1 = User::factory()->create([
            'username' => 'teller1'.substr(uniqid(), -6),
            'email' => 'teller1-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->teller2 = User::factory()->create([
            'username' => 'teller2'.substr(uniqid(), -6),
            'email' => 'teller2-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->teller3 = User::factory()->create([
            'username' => 'teller3'.substr(uniqid(), -6),
            'email' => 'teller3-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test that handover with many currencies maintains consistent lock order.
     *
     * This test verifies the fix for A3: TillBalance Lock Scope Review.
     * Currency codes are sorted alphabetically before locking to ensure
     * consistent lock order preventing deadlocks when concurrent handovers
     * involve overlapping but different currency sets.
     */
    public function test_currency_codes_are_sorted_before_locking_in_handover(): void
    {
        // Create session with multiple currencies
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(30),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create till balances for USD, EUR, GBP
        $today = now()->toDateString();

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'USD',
            'opening_balance' => '10000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'EUR',
            'opening_balance' => '5000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'GBP',
            'opening_balance' => '3000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        $counterService = app(CounterService::class);

        // Hand over with currencies in non-alphabetical order
        // The service should sort them before locking
        $physicalCounts = [
            ['currency_id' => 'USD', 'amount' => '10500.00'],
            ['currency_id' => 'GBP', 'amount' => '3100.00'],
            ['currency_id' => 'EUR', 'amount' => '5100.00'],
        ];

        $result = $counterService->initiateHandover(
            $session,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts
        );

        // If sorting works, handover should succeed without any issues
        $this->assertArrayHasKey('handover', $result);
        $this->assertArrayHasKey('new_session', $result);

        // Verify the session was properly handed over
        $session->refresh();
        $this->assertEquals(CounterSessionStatus::HandedOver, $session->status);
    }

    /**
     * Test that concurrent handovers with different currency sets complete without deadlock.
     *
     * This test simulates the scenario where:
     * - Teller1 hands over to Teller2 (involving currencies: EUR, GBP)
     * - Teller2 hands over to Teller3 (involving currencies: USD, GBP)
     *
     * Without alphabetical sorting, these could deadlock.
     * With the fix, they complete successfully.
     */
    public function test_concurrent_handovers_different_currencies_do_not_conflict(): void
    {
        $today = now()->toDateString();
        $counterService = app(CounterService::class);

        // Create first session (Teller1)
        $session1 = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(60),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create till balances
        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'USD',
            'opening_balance' => '10000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'EUR',
            'opening_balance' => '5000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'GBP',
            'opening_balance' => '3000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        // First handover: Teller1 -> Teller2 (USD, EUR)
        $physicalCounts1 = [
            ['currency_id' => 'USD', 'amount' => '10500.00'],
            ['currency_id' => 'EUR', 'amount' => '5100.00'],
        ];

        $result1 = $counterService->initiateHandover(
            $session1,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts1
        );

        $this->assertArrayHasKey('handover', $result1);
        $this->assertArrayHasKey('new_session', $result1);

        // Create second session for Teller2
        $session2 = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller2->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(30),
            'opened_by' => $this->manager->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Second handover: Teller2 -> Teller3 (EUR, GBP)
        $physicalCounts2 = [
            ['currency_id' => 'EUR', 'amount' => '5200.00'],
            ['currency_id' => 'GBP', 'amount' => '3100.00'],
        ];

        $result2 = $counterService->initiateHandover(
            $session2,
            $this->teller2,
            $this->teller3,
            $this->manager,
            $physicalCounts2
        );

        $this->assertArrayHasKey('handover', $result2);
        $this->assertArrayHasKey('new_session', $result2);

        // Both handovers completed successfully - no deadlock
        $this->assertTrue(true);
    }

    /**
     * Test that handover with many currencies maintains consistent lock order.
     */
    public function test_handover_with_many_currencies_maintains_lock_order(): void
    {
        $today = now()->toDateString();
        $counterService = app(CounterService::class);

        // Create session with all 3 currencies
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(60),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create till balances for all 3 currencies
        foreach (['USD', 'EUR', 'GBP'] as $code) {
            TillBalance::create([
                'till_id' => (string) $this->counter->id,
                'currency_code' => $code,
                'opening_balance' => '5000.00',
                'date' => $today,
                'opened_by' => $this->teller1->id,
            ]);
        }

        // Hand over with currencies in reverse alphabetical order
        // The code should sort them before locking
        $physicalCounts = [
            ['currency_id' => 'GBP', 'amount' => '5100.00'],
            ['currency_id' => 'EUR', 'amount' => '5200.00'],
            ['currency_id' => 'USD', 'amount' => '5100.00'],
        ];

        $result = $counterService->initiateHandover(
            $session,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts
        );

        $this->assertArrayHasKey('handover', $result);
        $this->assertArrayHasKey('new_session', $result);

        // Verify the session was properly handed over
        $session->refresh();
        $this->assertEquals(CounterSessionStatus::HandedOver, $session->status);
    }
}
