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
 * Tests for A8: Variance Notes Not in TillBalance
 *
 * During handover, variance notes were stored in CounterHandover but NOT in TillBalance.
 * This test verifies that variance notes are properly recorded in TillBalance.notes
 * when closing a session during handover.
 */
class CounterHandoverVarianceNotesTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Counter $counter;

    protected User $teller1;

    protected User $teller2;

    protected User $manager;

    protected Currency $usd;

    protected Currency $eur;

    protected function setUp(): void
    {
        parent::setUp();

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
     * Test that variance notes are recorded in TillBalance when handover has variance.
     *
     * A8: Variance Notes Not in TillBalance
     *
     * When a handover occurs with a variance, the variance notes should be stored
     * in TillBalance.notes so that the audit trail captures the variance details.
     */
    public function test_variance_notes_recorded_in_till_balance(): void
    {
        $today = now()->toDateString();

        // Create session
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(30),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create opening till balances
        $usdBalance = TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'USD',
            'opening_balance' => '10000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        $eurBalance = TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'EUR',
            'opening_balance' => '5000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        $counterService = app(CounterService::class);

        // Hand over with a variance (closing != opening)
        // USD: 10000 -> 10500 (variance +500)
        // EUR: 5000 -> 5000 (variance 0)
        $physicalCounts = [
            ['currency_id' => 'USD', 'amount' => '10500.00'],
            ['currency_id' => 'EUR', 'amount' => '5000.00'],
        ];

        $result = $counterService->initiateHandover(
            $session,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts
        );

        // Refresh till balances
        $usdBalance->refresh();
        $eurBalance->refresh();

        // The old USD balance should have closing info with variance notes
        $this->assertNotNull($usdBalance->closed_at, 'USD closed_at should not be null');
        $this->assertEquals('10500.00', $usdBalance->closing_balance);
        $this->assertNotNull($usdBalance->variance);
        $this->assertEquals('500.0000', $usdBalance->variance);
        $this->assertNotNull($usdBalance->notes);
        $this->assertStringContainsString('Variance during handover', $usdBalance->notes);
        $this->assertStringContainsString('USD', $usdBalance->notes);

        // EUR should have no variance, so notes should be 'Handover'
        $this->assertNotNull($eurBalance->closed_at);
        $this->assertEquals('5000.00', $eurBalance->closing_balance);
        $this->assertEquals('0.0000', $eurBalance->variance);
        $this->assertNotNull($eurBalance->notes);
        $this->assertEquals('Handover', $eurBalance->notes);
    }

    /**
     * Test that TillBalance.notes contains variance details when variance is non-zero.
     *
     * Verifies the complete variance notes format including all currencies with variances.
     */
    public function test_till_balance_notes_contains_all_currency_variances(): void
    {
        $today = now()->toDateString();

        // Create session with multiple currencies
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(30),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create opening till balances for multiple currencies
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

        $counterService = app(CounterService::class);

        // Hand over with variances on multiple currencies
        $physicalCounts = [
            ['currency_id' => 'USD', 'amount' => '10200.00'], // +200 variance
            ['currency_id' => 'EUR', 'amount' => '4800.00'], // -200 variance
        ];

        $result = $counterService->initiateHandover(
            $session,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts
        );

        $handover = $result['handover'];

        // Get the closed USD balance
        $closedUsdBalance = TillBalance::where('till_id', (string) $this->counter->id)
            ->where('currency_code', 'USD')
            ->whereNotNull('closed_at')
            ->first();

        $this->assertNotNull($closedUsdBalance);
        $this->assertStringContainsString('USD', $closedUsdBalance->notes ?? '');
        // Variance notes should mention both currencies
        $this->assertStringContainsString('EUR', $closedUsdBalance->notes ?? '');
    }

    /**
     * Test that when there is no variance, TillBalance.notes is just 'Handover'.
     */
    public function test_till_balance_notes_is_handover_when_no_variance(): void
    {
        $today = now()->toDateString();

        // Create session
        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->teller1->id,
            'session_date' => $today,
            'opened_at' => now()->subMinutes(30),
            'opened_by' => $this->teller1->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create opening till balance
        $usdBalance = TillBalance::create([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'USD',
            'opening_balance' => '10000.00',
            'date' => $today,
            'opened_by' => $this->teller1->id,
        ]);

        $counterService = app(CounterService::class);

        // Hand over with NO variance (closing == opening)
        $physicalCounts = [
            ['currency_id' => 'USD', 'amount' => '10000.00'],
        ];

        $result = $counterService->initiateHandover(
            $session,
            $this->teller1,
            $this->teller2,
            $this->manager,
            $physicalCounts
        );

        $usdBalance->refresh();

        // No variance, so notes should be 'Handover'
        $this->assertNotNull($usdBalance->notes, 'Notes should not be null even when no variance');
        $this->assertEquals('Handover', $usdBalance->notes);
        $this->assertEquals('0.0000', $usdBalance->variance);
    }
}
