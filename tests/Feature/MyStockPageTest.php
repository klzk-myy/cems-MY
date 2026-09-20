<?php

namespace Tests\Feature;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class MyStockPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'MYR'], ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_active' => true]);
    }

    #[Test]
    public function teller_sees_daily_stock_summary_matching_opening_buy_sell_current(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id ?? Branch::factory()->create()->id,
            'currency_code' => 'USD',
            'allocated_quantity' => '200.00',
            'current_quantity' => '100.00',
            'requested_quantity' => '200.00',
            'daily_limit_myr' => '500000.0000',
            'daily_used_myr' => '0.0000',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        // Buy 100 USD in, paying RM 401 out.
        Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '401.00',
            'rate' => '4.01',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        // Sell 200 USD out, receiving RM 802 in.
        Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '200.00',
            'amount_myr' => '802.00',
            'rate' => '4.01',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($teller)->get('/my-stock');

        $response->assertOk();
        $response->assertSee('USD');
        $response->assertSee('200.00');   // opening
        $response->assertSee('100.00');   // buy qty and current
        $response->assertSee('401.00');   // RM Cr
        $response->assertSee('802.00');   // RM Dr

        // The row math: Current = Opening + Buy - Sell = 200 + 100 - 200 = 100.
        $this->assertStringContainsString('100.00', (string) $response->getContent());
    }

    #[Test]
    public function date_filter_shows_the_selected_day_only(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        $today = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '50.00',
            'amount_myr' => '200.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $yesterday = Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '75.00',
            'amount_myr' => '300.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'created_at' => now()->subDay(),
            'idempotency_key' => uniqid('test_', true),
        ]);

        $todayResponse = $this->actingAs($teller)->get('/my-stock');
        $todayResponse->assertSee('50.00');
        $todayResponse->assertDontSee('75.00');

        $yesterdayResponse = $this->actingAs($teller)->get('/my-stock?date='.now()->subDay()->format('Y-m-d'));
        $yesterdayResponse->assertSee('75.00');
        $yesterdayResponse->assertDontSee('>50.00<');
    }

    #[Test]
    public function reversed_transactions_and_refunds_are_excluded(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '60.00',
            'amount_myr' => '240.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Reversed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '60.00',
            'amount_myr' => '240.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'is_refund' => true,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($teller)->get('/my-stock');

        $response->assertOk();
        // Neither the reversed original nor the refund record may appear.
        $response->assertDontSee('>60.00<');
        $response->assertDontSee('>240.00<');
    }

    #[Test]
    public function myr_row_reports_cash_paid_and_received(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $customer = $this->createTestCustomer();

        TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id ?? Branch::factory()->create()->id,
            'currency_code' => 'MYR',
            'allocated_quantity' => '10000.00',
            'current_quantity' => '10000.00',
            'requested_quantity' => '10000.00',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        // Buy: RM 1000 out. Sell: RM 2500 in. Expected MYR current = 10000 - 1000 + 2500 = 11500.
        Transaction::factory()->create([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '250.00',
            'amount_myr' => '1000.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);
        Transaction::factory()->create([
            'type' => TransactionType::Sell,
            'currency_code' => 'USD',
            'quantity' => '625.00',
            'amount_myr' => '2500.00',
            'rate' => '4.00',
            'customer_id' => $customer->id,
            'user_id' => $teller->id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ]);

        $response = $this->actingAs($teller)->get('/my-stock');

        $response->assertOk();
        $response->assertSee('MYR');
        $response->assertSee('10,000.00');
        $response->assertSee('1,000.00');
        $response->assertSee('2,500.00');
        $response->assertSee('11,500.00');
    }

    #[Test]
    public function page_shows_total_value_of_stock_and_cash_in_myr(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id ?? Branch::factory()->create()->id,
            'currency_code' => 'USD',
            'allocated_quantity' => '200.00',
            'current_quantity' => '200.00',
            'requested_quantity' => '200.00',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);
        TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id ?? Branch::factory()->create()->id,
            'currency_code' => 'MYR',
            'allocated_quantity' => '10000.00',
            'current_quantity' => '10000.00',
            'requested_quantity' => '10000.00',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        // Future fetched_at guarantees this card outranks seeded board rates.
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_sell' => '4.0000',
            'fetched_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($teller)->get('/my-stock');

        $response->assertOk();
        // 200 USD @ 4.00 = MYR 800 foreign stock; MYR 10,000 cash; total 10,800.
        $response->assertSee('MYR 800.00');
        $response->assertSee('MYR 10,000.00');
        $response->assertSee('MYR 10,800.00');
    }

    #[Test]
    public function currency_without_rate_is_flagged_not_silently_dropped(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        Currency::firstOrCreate(['code' => 'ZZZ'], ['name' => 'Test Currency', 'symbol' => 'Z', 'decimal_places' => 2, 'is_active' => true]);

        TellerAllocation::create([
            'user_id' => $teller->id,
            'branch_id' => $teller->branch_id ?? Branch::factory()->create()->id,
            'currency_code' => 'ZZZ',
            'allocated_quantity' => '500.00',
            'current_quantity' => '500.00',
            'requested_quantity' => '500.00',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($teller)->get('/my-stock');

        $response->assertOk();
        $response->assertSee('No active rate for: ZZZ');
    }

    #[Test]
    public function roles_without_create_transactions_cannot_view(): void
    {
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $this->actingAs($accountant)->get('/my-stock')->assertForbidden();
    }
}
