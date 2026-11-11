<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTransaction(array $overrides = []): Transaction
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $transaction = Transaction::factory()->create(array_merge([
            'status' => TransactionStatus::Completed->value,
            'type' => TransactionType::Buy->value,
            'currency_code' => 'USD',
            'amount_myr' => '100.0000',
            'quantity' => '25.0000',
            'rate' => '4.00000000',
        ], $overrides));

        if ($createdAt !== null) {
            $transaction->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();
        }

        return $transaction;
    }

    protected function rebuild(string $from, string $to): void
    {
        app(AnalyticsSnapshotBuilder::class)->rebuildRange(Carbon::parse($from), Carbon::parse($to));
    }

    #[Test]
    public function bi_report_pages_require_authentication_and_permission(): void
    {
        foreach (['bi.branches', 'bi.currencies', 'bi.operations', 'bi.customers'] as $route) {
            $this->get(route($route))->assertRedirect();
        }

        $teller = User::factory()->teller()->create();
        foreach (['bi.branches', 'bi.currencies', 'bi.operations', 'bi.customers'] as $route) {
            $this->actingAs($teller)->get(route($route))->assertForbidden();
        }
    }

    #[Test]
    public function branches_page_compares_all_branches_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $north = Branch::factory()->create(['name' => 'North Branch']);
        $south = Branch::factory()->create(['name' => 'South Branch']);
        $this->makeTransaction(['branch_id' => $north->id, 'amount_myr' => '700.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $south->id, 'amount_myr' => '300.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.branches'))
            ->assertOk()
            ->assertSee('North Branch')
            ->assertSee('South Branch')
            ->assertSee('700.00')
            ->assertSee('300.00');
    }

    #[Test]
    public function branches_page_shows_scoped_viewer_only_their_branch(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->branch->update(['name' => 'Own Branch']);
        $other = Branch::factory()->create(['name' => 'Foreign Branch']);
        $this->makeTransaction(['branch_id' => $manager->branch_id, 'amount_myr' => '400.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $other->id, 'amount_myr' => '800.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($manager)
            ->get(route('bi.branches'))
            ->assertOk()
            ->assertSee('Own Branch')
            ->assertSee('400.00')
            ->assertDontSee('Foreign Branch')
            ->assertDontSee('800.00');
    }

    #[Test]
    public function currencies_page_breaks_down_and_filters_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'USD', 'amount_myr' => '600.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $branch->id, 'currency_code' => 'EUR', 'amount_myr' => '400.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.currencies'))
            ->assertOk()
            ->assertSee('Currency Breakdown')
            ->assertSee('USD')
            ->assertSee('EUR')
            ->assertSee('600.00')
            ->assertSee('400.00');
    }

    #[Test]
    public function operations_page_lists_teller_productivity(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        $teller = User::factory()->teller()->create(['branch_id' => $branch->id, 'username' => 'Teller Jane']);
        $this->makeTransaction(['branch_id' => $branch->id, 'user_id' => $teller->id, 'amount_myr' => '500.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.operations'))
            ->assertOk()
            ->assertSee('Teller Productivity')
            ->assertSee('Teller Jane')
            ->assertSee('500.00');
    }

    #[Test]
    public function customers_page_shows_top_customers_and_trend(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['full_name' => 'Valued Client']);
        $this->makeTransaction(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'amount_myr' => '900.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($admin)
            ->get(route('bi.customers'))
            ->assertOk()
            ->assertSee('Top Customers')
            ->assertSee('Valued Client')
            ->assertSee('900.00');
    }

    #[Test]
    public function currencies_page_respects_branch_scope(): void
    {
        $manager = User::factory()->manager()->create();
        $other = Branch::factory()->create();
        $this->makeTransaction(['branch_id' => $manager->branch_id, 'currency_code' => 'USD', 'amount_myr' => '222.0000', 'created_at' => today()]);
        $this->makeTransaction(['branch_id' => $other->id, 'currency_code' => 'USD', 'amount_myr' => '888.0000', 'created_at' => today()]);
        $this->rebuild(today()->toDateString(), today()->toDateString());

        $this->actingAs($manager)
            ->get(route('bi.currencies'))
            ->assertOk()
            ->assertSee('222.00')
            ->assertDontSee('888.00');
    }
}
