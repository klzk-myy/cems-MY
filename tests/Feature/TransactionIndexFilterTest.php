<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slow')]
class TransactionIndexFilterTest extends TestCase
{
    use DatabaseTransactions;

    private User $teller;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true]);

        $this->teller = User::factory()->create(['role' => UserRole::Teller]);
        $this->customerId = $this->createTestCustomer()->id;
    }

    private function makeTransaction(array $overrides = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'type' => TransactionType::Buy,
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'amount_myr' => '400.00',
            'rate' => '4.00',
            'customer_id' => $this->customerId,
            'user_id' => $this->teller->id,
            'branch_id' => $this->teller->branch_id,
            'status' => TransactionStatus::Completed,
            'idempotency_key' => uniqid('test_', true),
        ], $overrides));
    }

    #[Test]
    public function filters_by_type(): void
    {
        $buy = $this->makeTransaction(['type' => TransactionType::Buy]);
        $sell = $this->makeTransaction(['type' => TransactionType::Sell]);

        $response = $this->actingAs($this->teller)->get('/transactions?type=Buy');

        $response->assertOk();
        $response->assertSee('TX-'.str_pad((string) $buy->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $sell->id, 8, '0', STR_PAD_LEFT));
    }

    #[Test]
    public function filters_by_currency(): void
    {
        $usd = $this->makeTransaction(['currency_code' => 'USD']);
        $eur = $this->makeTransaction(['currency_code' => 'EUR']);

        $response = $this->actingAs($this->teller)->get('/transactions?currency_code=EUR');

        $response->assertOk();
        $response->assertSee('TX-'.str_pad((string) $eur->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $usd->id, 8, '0', STR_PAD_LEFT));
    }

    #[Test]
    public function filters_by_date_range(): void
    {
        $old = $this->makeTransaction(['created_at' => now()->subDays(10)]);
        $recent = $this->makeTransaction(['created_at' => now()->subDay()]);

        $response = $this->actingAs($this->teller)->get('/transactions?date_from='.now()->subDays(3)->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('TX-'.str_pad((string) $recent->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $old->id, 8, '0', STR_PAD_LEFT));

        $response = $this->actingAs($this->teller)->get('/transactions?date_to='.now()->subDays(5)->format('Y-m-d'));

        $response->assertSee('TX-'.str_pad((string) $old->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $recent->id, 8, '0', STR_PAD_LEFT));
    }

    #[Test]
    public function filters_by_refund_flag(): void
    {
        $normal = $this->makeTransaction();
        $refund = $this->makeTransaction(['is_refund' => true]);

        $response = $this->actingAs($this->teller)->get('/transactions?is_refund=1');

        $response->assertOk();
        $response->assertSee('TX-'.str_pad((string) $refund->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $normal->id, 8, '0', STR_PAD_LEFT));

        $response = $this->actingAs($this->teller)->get('/transactions?is_refund=0');

        $response->assertSee('TX-'.str_pad((string) $normal->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $refund->id, 8, '0', STR_PAD_LEFT));
    }

    #[Test]
    public function filters_by_status_and_combines_with_other_filters(): void
    {
        $completed = $this->makeTransaction(['status' => TransactionStatus::Completed]);
        $pending = $this->makeTransaction(['status' => TransactionStatus::PendingApproval]);

        $response = $this->actingAs($this->teller)->get('/transactions?status=pending_approval');

        $response->assertOk();
        $response->assertSee('TX-'.str_pad((string) $pending->id, 8, '0', STR_PAD_LEFT));
        $response->assertDontSee('TX-'.str_pad((string) $completed->id, 8, '0', STR_PAD_LEFT));

        // Combined: pending + Sell should exclude the pending Buy.
        $response = $this->actingAs($this->teller)->get('/transactions?status=pending_approval&type=Sell');

        $response->assertDontSee('TX-'.str_pad((string) $pending->id, 8, '0', STR_PAD_LEFT));
        $response->assertSee('No transactions found');
    }

    #[Test]
    public function rejects_invalid_filter_values(): void
    {
        $this->makeTransaction();

        $this->actingAs($this->teller)
            ->get('/transactions?type=NotAType')
            ->assertSessionHasErrors('type');

        $this->actingAs($this->teller)
            ->get('/transactions?date_from=not-a-date')
            ->assertSessionHasErrors('date_from');

        $this->actingAs($this->teller)
            ->get('/transactions?date_from=2030-01-10&date_to=2030-01-01')
            ->assertSessionHasErrors('date_to');
    }
}
