<?php

namespace Tests\Feature\Api;

use App\Enums\CounterSessionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EodCounterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function counter_reconciliation_endpoint_returns_report_for_seeded_day(): void
    {
        $date = now()->toDateString();

        $branch = Branch::factory()->create();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'branch_id' => $branch->id,
        ]);
        $teller = User::factory()->create([
            'role' => UserRole::Teller,
            'branch_id' => $branch->id,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();

        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);

        // Direct inserts keep session_date as a bare 'Y-m-d' — the model's
        // date cast writes 'Y-m-d 00:00:00' on sqlite, which the service's
        // equality lookup misses (MySQL truncates server-side).
        DB::table('counter_sessions')->insert([
            'counter_id' => $counter->id,
            'user_id' => $teller->id,
            'session_date' => $date,
            'opened_at' => now()->subHours(8),
            'opened_by' => $manager->id,
            'closed_at' => now(),
            'closed_by' => $manager->id,
            'status' => CounterSessionStatus::Closed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('till_balances')->insert([
            'till_id' => (string) $counter->code,
            'currency_code' => 'MYR',
            'branch_id' => $branch->id,
            'opening_balance' => '10000.00',
            'closing_balance' => '15000.00',
            'date' => $date,
            'opened_by' => $teller->id,
            'closed_by' => $manager->id,
        ]);

        DB::table('transactions')->insert([
            'type' => TransactionType::Sell->value,
            'status' => TransactionStatus::Completed->value,
            'currency_code' => 'USD',
            'amount_myr' => '5000.00',
            'quantity' => '1100.00',
            'rate' => '4.5455',
            'till_id' => (string) $counter->code,
            'branch_id' => $branch->id,
            'user_id' => $teller->id,
            'customer_id' => $customer->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
            'cdd_level' => 'Simplified',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/eod/reconciliation/{$date}/counters/{$counter->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'counter_id',
                    'has_session',
                    'session' => ['id', 'status'],
                    'opening_float',
                    'total_cash_received',
                    'total_cash_paid_out',
                    'closing_float_expected',
                    'closing_float_actual',
                    'variance',
                    'transactions' => ['total_count', 'buy_count', 'sell_count'],
                    'large_transactions',
                    'flagged_transactions',
                    'handover_history',
                ],
            ])
            ->assertJsonPath('data.has_session', true)
            ->assertJsonPath('data.transactions.sell_count', 1);
    }
}
