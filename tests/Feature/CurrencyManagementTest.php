<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $code = 'XYZ', array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => 'Test Currency',
            'symbol' => 'X$',
            'decimal_places' => '2',
        ], $overrides);
    }

    #[Test]
    public function admin_can_create_a_currency(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload())
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('currencies', [
            'code' => 'XYZ',
            'name' => 'Test Currency',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function created_currency_appears_in_form_selects(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload());

        // The transaction form builds its currency select from
        // Currency::where('is_active', true) — the same source every form uses.
        $this->actingAs($admin)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('XYZ');
    }

    #[Test]
    public function created_currency_is_mapped_into_accounting_for_every_active_branch(): void
    {
        $admin = User::factory()->admin()->create();
        Branch::factory()->inactive()->create();
        $activeCount = Branch::where('is_active', true)->count();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload())
            ->assertRedirect(route('system.currencies.index'));

        foreach (Branch::where('is_active', true)->get() as $branch) {
            $this->assertDatabaseHas('branch_pools', [
                'branch_id' => $branch->id,
                'currency_code' => 'XYZ',
                'available_balance' => '0.0000',
            ]);
            $this->assertDatabaseHas('currency_positions', [
                'branch_id' => $branch->id,
                'currency_code' => 'XYZ',
                'quantity' => '0',
            ]);
        }

        // Inactive branches are not provisioned.
        $this->assertSame($activeCount, CurrencyPosition::where('currency_code', 'XYZ')->count());
        $this->assertSame($activeCount, BranchPool::where('currency_code', 'XYZ')->count());
    }

    #[Test]
    public function code_must_be_uppercase_alpha3(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload('xy1'))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('currencies', ['code' => 'xy1']);
    }

    #[Test]
    public function duplicate_code_of_active_currency_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Currency::factory()->create(['code' => 'USD']);

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload('USD'))
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function decimal_places_must_be_between_0_and_4(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload('ABC', ['decimal_places' => '5']))
            ->assertSessionHasErrors('decimal_places');

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload('ABC', ['decimal_places' => '-1']))
            ->assertSessionHasErrors('decimal_places');
    }

    #[Test]
    public function disable_is_blocked_while_open_transactions_reference_the_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        Transaction::factory()->create([
            'currency_code' => 'USD',
            'status' => TransactionStatus::PendingApproval,
        ]);

        $this->actingAs($admin)
            ->from(route('system.currencies.index'))
            ->post(route('system.currencies.disable', $currency))
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('error');

        $this->assertTrue($currency->refresh()->is_active);
    }

    #[Test]
    public function disable_is_blocked_with_non_zero_positions(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'balance' => 5000,
        ]);

        $this->actingAs($admin)
            ->post(route('system.currencies.disable', $currency))
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('error');

        $this->assertTrue($currency->refresh()->is_active);
    }

    #[Test]
    public function disabled_currency_is_hidden_from_selects_but_still_listed(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        Transaction::query()->where('currency_code', 'USD')->delete();

        $this->actingAs($admin)
            ->post(route('system.currencies.disable', $currency))
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('success');

        $this->assertFalse($currency->refresh()->is_active);

        // Visit the index first — this consumes the "Currency USD disabled."
        // flash so it does not leak into the create-page assertions below.
        $this->actingAs($admin)
            ->get(route('system.currencies.index'))
            ->assertOk()
            ->assertSee('USD')
            ->assertSee('Disabled');

        // Hidden from form selects...
        $this->actingAs($admin)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertDontSee('USD');
    }

    #[Test]
    public function historical_transaction_rows_still_render_after_disable(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        $transaction = Transaction::factory()->create([
            'currency_code' => 'USD',
            'status' => TransactionStatus::Completed,
        ]);

        $this->actingAs($admin)
            ->post(route('system.currencies.disable', $currency))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertSee('USD');
    }

    #[Test]
    public function non_admin_cannot_manage_currencies(): void
    {
        $manager = User::factory()->manager()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        $this->actingAs($manager)
            ->get(route('system.currencies.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('system.currencies.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('system.currencies.disable', $currency))
            ->assertForbidden();

        $this->assertTrue($currency->refresh()->is_active);
    }
}
