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
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function variance_returns_expected_for_unclosed_sessions(): void
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
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => null, // Session not closed
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
        ]);

        // Create a sell transaction (MYR received) using direct insert
        DB::table('transactions')->insert([
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->code,
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

        // Expected closing = opening + cashReceived(sells) - cashPaidOut(buys) = 10000 + 5000 - 0 = 15000
        // Since session is unclosed, variance should return expected closing (not 0)
        $this->assertEquals('15000.0000', $variance);
    }

    #[Test]
    public function variance_returns_calculated_difference_when_session_closed(): void
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
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '14800.00', // Actual closing shows RM 200 short
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
        ]);

        // Create a sell transaction (MYR received) using direct insert
        DB::table('transactions')->insert([
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->code,
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
        $this->assertEquals('-200.0000', $variance);
    }

    #[Test]
    public function variance_returns_expected_closing_for_unclosed_session_with_no_transactions(): void
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
            'till_id' => (string) $this->counter->code,
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
        $this->assertEquals('10000.0000', $variance);
    }

    #[Test]
    public function pending_transactions_excluded_from_eod_variance(): void
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
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '15000.00', // Expected = 15000, Actual = 15000, Variance = 0
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
        ]);

        // Create a completed Sell transaction (should be included in variance)
        DB::table('transactions')->insert([
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->code,
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
            'till_id' => (string) $this->counter->code,
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
        $this->assertEquals('0.0000', $variance);
    }

    #[Test]
    public function counter_reconciliation_response_shape_is_stable(): void
    {
        $date = Carbon::today();

        // Direct insert: the model's `date` cast serializes session_date as
        // 'Y-m-d 00:00:00' on sqlite, which the service's string-equality
        // lookup would miss (MySQL DATE columns truncate server-side).
        DB::table('counter_sessions')->insert([
            'counter_id' => $this->counter->id,
            'user_id' => $this->user->id,
            'session_date' => $date->toDateString(),
            'opened_at' => now()->subHours(8),
            'opened_by' => $this->user->id,
            'closed_at' => now(),
            'closed_by' => $this->user->id,
            'status' => CounterSessionStatus::Closed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('till_balances')->insert([
            'till_id' => (string) $this->counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $this->branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '14000.00',
            'date' => $date->toDateString(),
            'opened_by' => $this->user->id,
            'closed_by' => $this->user->id,
        ]);

        // One Sell (MYR in) and one Buy (MYR out) so the transaction section
        // has non-zero counts on both sides.
        DB::table('transactions')->insert([
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '5000.00',
            'amount_foreign' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->code,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);
        DB::table('transactions')->insert([
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_local' => '1000.00',
            'amount_foreign' => '220.00',
            'rate' => '4.5455',
            'till_id' => (string) $this->counter->code,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        $report = $this->service->generateCounterReconciliation($this->counter->id, $date);

        // Snapshot the key set + order — the API response shape is contractual.
        $this->assertSame([
            'counter_id',
            'counter_code',
            'counter_name',
            'branch_name',
            'date',
            'has_session',
            'session',
            'opening_float',
            'total_cash_received',
            'total_cash_paid_out',
            'closing_float_expected',
            'closing_float_actual',
            'variance',
            'currency_breakdown',
            'transactions',
            'large_transactions',
            'flagged_transactions',
            'handover_history',
        ], array_keys($report));

        $this->assertSame([
            'total_count',
            'buy_count',
            'sell_count',
            'buy_total',
            'sell_total',
        ], array_keys($report['transactions']));

        $this->assertSame([
            'id',
            'status',
            'opened_at',
            'closed_at',
            'opened_by',
            'closed_by',
            'current_user',
        ], array_keys($report['session']));

        // Counts pair with transaction type; totals carry the cash-flow sums
        // (legacy pairing — buy_total is cash received).
        $this->assertSame(1, $report['transactions']['buy_count']);
        $this->assertSame(1, $report['transactions']['sell_count']);
        $this->assertSame('5000.0000', $report['transactions']['buy_total']);
        $this->assertSame('1000.0000', $report['transactions']['sell_total']);
        $this->assertSame('10000.0000', $report['opening_float']);
        $this->assertSame('14000.0000', $report['closing_float_expected']);
        $this->assertSame('14000.0000', $report['closing_float_actual']);
    }

    #[Test]
    public function counter_reconciliation_reports_no_session_for_missing_session(): void
    {
        $report = $this->service->generateCounterReconciliation($this->counter->id, Carbon::today());

        $this->assertSame([
            'counter_id',
            'counter_code',
            'counter_name',
            'branch_name',
            'date',
            'has_session',
            'message',
        ], array_keys($report));
        $this->assertFalse($report['has_session']);
    }
}
