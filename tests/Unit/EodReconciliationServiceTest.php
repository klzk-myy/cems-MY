<?php

namespace Tests\Unit;

use App\Enums\CounterSessionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\EodReconciliationService;
use App\Services\ThresholdService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EodReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EodReconciliationService $service;

    protected Branch $branch;

    protected Counter $counter;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $thresholdService = app(ThresholdService::class);
        $this->service = new EodReconciliationService($thresholdService);

        // Use factory create which properly sets up relationships
        $this->branch = Branch::factory()->create();
        $this->counter = Counter::factory()->create(['branch_id' => $this->branch->id]);
        $this->user = User::factory()->create(['role' => 'teller']);
        $this->customer = Customer::factory()->create();

        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);
    }

    public function test_variance_returns_expected_for_unclosed_sessions(): void
    {
        $date = Carbon::today();

        // Create a counter session that is still open (not closed)
        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => $date->toDateString(),
            'opened_at' => now(),
            'opened_by' => $this->user->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create till balance using direct insert (bypassing factory to ensure proper till_id)
        DB::table('till_balances')->insert([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => null, // Session not closed
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
        ]);

        // Create some buy transactions (cash received) using direct insert
        DB::table('transactions')->insert([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        // Calculate variance
        $variance = $this->service->calculateVariance($this->counter->id, $date);

        // Expected closing = opening + buyTotal - sellTotal = 10000 + 5000 - 0 = 15000
        // Since session is unclosed, variance should return expected closing (not 0)
        $this->assertEquals('15000.000000', $variance);
    }

    public function test_variance_returns_calculated_difference_when_session_closed(): void
    {
        $date = Carbon::today();

        // Create a counter session that is closed
        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => $date->toDateString(),
            'opened_at' => now()->subHours(8),
            'opened_by' => $this->user->id,
            'closed_at' => now(),
            'closed_by' => $this->user->id,
            'status' => CounterSessionStatus::Closed,
        ]);

        // Create till balance with both opening and closing using direct insert
        DB::table('till_balances')->insert([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '14800.00', // Actual closing shows RM 200 short
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
        ]);

        // Create some transactions using direct insert
        DB::table('transactions')->insert([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        // Calculate variance
        $variance = $this->service->calculateVariance($this->counter->id, $date);

        // Expected closing = 10000 + 5000 = 15000
        // Actual closing = 14800
        // Variance = 14800 - 15000 = -200
        $this->assertEquals('-200.000000', $variance);
    }

    public function test_variance_returns_expected_closing_for_unclosed_session_with_no_transactions(): void
    {
        $date = Carbon::today();

        // Create a counter session that is still open (not closed)
        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => $date->toDateString(),
            'opened_at' => now(),
            'opened_by' => $this->user->id,
            'status' => CounterSessionStatus::Open,
        ]);

        // Create till balance using direct insert (bypassing factory to ensure proper till_id)
        DB::table('till_balances')->insert([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => null,
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
        ]);

        // Calculate variance (no transactions to affect expected)
        $variance = $this->service->calculateVariance($this->counter->id, $date);

        // Expected closing = opening + 0 - 0 = opening = 10000
        // Since session is unclosed, variance should return expected closing
        $this->assertEquals('10000.000000', $variance);
    }

    public function test_pending_transactions_excluded_from_eod_variance(): void
    {
        $date = Carbon::today();

        // Create a closed counter session
        CounterSession::create([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => $date->toDateString(),
            'opened_at' => now()->subHours(8),
            'opened_by' => $this->user->id,
            'closed_at' => now(),
            'closed_by' => $this->user->id,
            'status' => CounterSessionStatus::Closed,
        ]);

        // Create till balance with closing balance matching only completed transactions
        DB::table('till_balances')->insert([
            'till_id' => (string) $this->counter->id,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '15000.00', // Expected = 15000, Actual = 15000, Variance = 0
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
        ]);

        // Create a completed Buy transaction (should be included in variance)
        DB::table('transactions')->insert([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        // Create a Pending Buy transaction (should NOT be included in variance)
        DB::table('transactions')->insert([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Pending->value,
            'currency_code' => 'USD',
            'amount_local' => '3000.00',
            'amount_foreign' => '660.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        // Calculate variance
        $variance = $this->service->calculateVariance($this->counter->id, $date);

        // Expected closing = 10000 + 5000 (only completed) - 0 = 15000
        // Actual closing = 15000
        // Variance = 15000 - 15000 = 0 (Pending transaction excluded)
        $this->assertEquals('0.000000', $variance);
    }
}
