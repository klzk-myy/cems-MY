<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CurrencyAccountProvisioner;
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
    public function created_currency_is_mapped_into_accounting_for_every_active_branch(): void
    {
        $admin = User::factory()->admin()->create();
        Branch::factory()->inactive()->create();
        $activeCount = Branch::where('is_active', true)->count();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload())
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('currencies', [
            'code' => 'XYZ',
            'name' => 'Test Currency',
            'is_active' => true,
        ]);

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
    public function created_currency_gets_dedicated_gl_accounts_and_mappings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('system.currencies.store'), $this->payload())
            ->assertRedirect(route('system.currencies.index'));

        $cash = ChartOfAccount::where('account_name', 'Cash (XYZ)')->first();
        $inventory = ChartOfAccount::where('account_name', 'Forex Inventory (XYZ)')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($inventory);
        $this->assertSame('Cash', $cash->account_class);
        $this->assertSame('Inventory', $inventory->account_class);

        $this->assertSame($cash->account_code, AccountMapping::where('key', 'cash.XYZ')->value('account_code'));
        $this->assertSame($inventory->account_code, AccountMapping::where('key', 'inventory.XYZ')->value('account_code'));

        // Posting paths resolve the dedicated accounts at runtime.
        $mappings = app(AccountMappingService::class);
        $this->assertSame($inventory->account_code, $mappings->forCurrency('inventory', 'XYZ'));
        $this->assertSame($cash->account_code, $mappings->forCurrency('cash', 'XYZ'));

        $this->assertDatabaseHas('system_logs', [
            'action' => 'currency_accounts_provisioned',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function provision_maps_enum_covered_currencies_to_their_dedicated_accounts(): void
    {
        // USD has dedicated enum accounts (1001/2001) seeded in the chart —
        // provisioning must map to them instead of allocating new codes.
        $resolved = app(CurrencyAccountProvisioner::class)->provision(Currency::findOrFail('USD'));

        $this->assertSame('1001', $resolved['cash']);
        $this->assertSame('2001', $resolved['inventory']);
        $this->assertSame('1001', AccountMapping::where('key', 'cash.USD')->value('account_code'));
        $this->assertSame('2001', AccountMapping::where('key', 'inventory.USD')->value('account_code'));
    }

    #[Test]
    public function provision_is_idempotent_and_respects_existing_rows(): void
    {
        $provisioner = app(CurrencyAccountProvisioner::class);
        $usd = Currency::findOrFail('USD');

        // An existing (possibly remapped) row always wins.
        AccountMapping::create([
            'key' => 'inventory.USD',
            'account_code' => '2000',
            'description' => 'manual override',
        ]);

        $resolved = $provisioner->provision($usd);

        $this->assertSame('1001', $resolved['cash']);
        $this->assertSame('2000', $resolved['inventory']);

        $second = $provisioner->provision($usd);
        $this->assertSame($resolved, $second);
        $this->assertSame(1, AccountMapping::where('key', 'inventory.USD')->count());
    }

    #[Test]
    public function provision_skips_the_base_currency(): void
    {
        $resolved = app(CurrencyAccountProvisioner::class)->provision(Currency::findOrFail('MYR'));

        $this->assertSame(['cash' => null, 'inventory' => null], $resolved);
        $this->assertNull(AccountMapping::where('key', 'cash.MYR')->first());
    }

    #[Test]
    public function provision_allocates_a_window_code_when_the_enum_account_is_unusable(): void
    {
        // The canonical USD cash account exists but is inactive — the
        // provisioner must not map cash.USD to an account the management
        // page's own validation would reject; it allocates from the window.
        ChartOfAccount::where('account_code', '1001')->update(['is_active' => false]);

        $resolved = app(CurrencyAccountProvisioner::class)->provision(Currency::findOrFail('USD'));

        $this->assertSame('1008', $resolved['cash']);
        $this->assertSame('2001', $resolved['inventory']);

        $allocated = ChartOfAccount::find('1008');
        $this->assertNotNull($allocated);
        $this->assertSame('Cash (USD)', $allocated->account_name);
        $this->assertTrue($allocated->is_active);
        $this->assertSame('1008', AccountMapping::where('key', 'cash.USD')->value('account_code'));
    }

    #[Test]
    public function provision_allocates_a_window_code_when_the_enum_account_has_the_wrong_type(): void
    {
        // Same guard for a mistyped row: 1001 as Revenue must not be mapped.
        ChartOfAccount::where('account_code', '1001')->update(['account_type' => 'Revenue']);

        $resolved = app(CurrencyAccountProvisioner::class)->provision(Currency::findOrFail('USD'));

        $this->assertSame('1008', $resolved['cash']);
        $this->assertSame('1008', AccountMapping::where('key', 'cash.USD')->value('account_code'));
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

        // Hidden from form selects — the create form is teller-only.
        $teller = User::factory()->create(['role' => 'teller']);

        $this->actingAs($teller)
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
    public function index_page_does_not_expose_inline_quote_unit_editing(): void
    {
        $admin = User::factory()->admin()->create();
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $this->actingAs($admin)
            ->get(route('system.currencies.index'))
            ->assertOk()
            ->assertDontSee('Quote Unit')
            ->assertDontSee('rate-unit', false);
    }

    #[Test]
    public function admin_can_set_quote_direction_to_inverse(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'IDR']);

        $response = $this->actingAs($admin)
            ->put(route('system.currencies.update', $currency), [
                'name' => 'Indonesian Rupiah',
                'decimal_places' => '0',
                'rate_unit' => '1',
                'rate_inverse' => '1',
            ]);

        $response->assertRedirect(route('system.currencies.index'));

        $currency->refresh();
        $this->assertSame(1, (int) $currency->rate_unit);
        $this->assertTrue((bool) $currency->rate_inverse);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'rate_unit_changed',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function edit_form_sets_quote_unit_and_direction(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'IDR']);

        $this->actingAs($admin)
            ->get(route('system.currencies.edit', $currency))
            ->assertOk()
            ->assertSee('name="rate_unit"', false)
            ->assertSee('name="rate_inverse"', false);

        $response = $this->actingAs($admin)
            ->put(route('system.currencies.update', $currency), [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'decimal_places' => '0',
                'rate_unit' => '1000000',
                'rate_inverse' => '1',
            ]);

        $response->assertRedirect(route('system.currencies.index'));
        $response->assertSessionHas('success');

        $currency->refresh();
        $this->assertSame(1000000, (int) $currency->rate_unit);
        $this->assertTrue((bool) $currency->rate_inverse);
        $this->assertSame('Indonesian Rupiah', $currency->name);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'rate_unit_changed',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function edit_form_requires_valid_quote_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'IDR']);

        $this->actingAs($admin)
            ->put(route('system.currencies.update', $currency), [
                'name' => 'Indonesian Rupiah',
                'decimal_places' => '0',
                'rate_unit' => '0',
                'rate_inverse' => '0',
            ])
            ->assertSessionHasErrors('rate_unit');

        $this->assertSame(1, (int) $currency->refresh()->rate_unit);
    }

    #[Test]
    public function quote_unit_must_be_a_positive_integer(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'IDR']);

        $this->actingAs($admin)
            ->put(route('system.currencies.update', $currency), [
                'name' => 'Indonesian Rupiah',
                'decimal_places' => '0',
                'rate_unit' => '0',
                'rate_inverse' => '0',
            ])
            ->assertSessionHasErrors('rate_unit');

        $this->assertSame(1, (int) $currency->refresh()->rate_unit);
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

        $this->actingAs($manager)
            ->put(route('system.currencies.update', $currency), [
                'name' => 'US Dollar',
                'decimal_places' => '2',
                'rate_unit' => '1000',
                'rate_inverse' => '0',
            ])
            ->assertForbidden();

        $this->assertTrue($currency->refresh()->is_active);
        $this->assertSame(1, (int) $currency->rate_unit);
    }

    #[Test]
    public function enable_reactivates_a_disabled_currency_and_provisions_missing_gl_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        // A currency disabled before dedicated-account provisioning existed
        // has no cash.{CCY}/inventory.{CCY} mappings — re-enabling must
        // complete the accounting footprint, not just flip the flag.
        $currency = Currency::factory()->create(['code' => 'ZZZ', 'is_active' => false]);

        // A branch created while the currency was disabled has no pool or
        // position rows for it — re-enable must backfill them.
        $branch = Branch::factory()->create(['type' => Branch::TYPE_BRANCH, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('system.currencies.enable', $currency))
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('success');

        $this->assertTrue($currency->refresh()->is_active);
        $this->assertDatabaseHas('account_mappings', ['key' => 'cash.ZZZ']);
        $this->assertDatabaseHas('account_mappings', ['key' => 'inventory.ZZZ']);
        $this->assertDatabaseHas('branch_pools', [
            'branch_id' => $branch->id,
            'currency_code' => 'ZZZ',
        ]);
        $this->assertDatabaseHas('currency_positions', [
            'branch_id' => (string) $branch->id,
            'currency_code' => 'ZZZ',
        ]);
        $this->assertDatabaseHas('system_logs', ['action' => 'currency_enabled']);
    }

    #[Test]
    public function enable_rejects_an_already_active_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->create(['code' => 'ZZY', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('system.currencies.enable', $currency))
            ->assertRedirect(route('system.currencies.index'))
            ->assertSessionHas('error');

        $this->assertTrue($currency->refresh()->is_active);
        $this->assertDatabaseMissing('system_logs', ['action' => 'currency_enabled']);
    }

    #[Test]
    public function enable_requires_manage_currencies_permission(): void
    {
        $manager = User::factory()->manager()->create();
        $currency = Currency::factory()->create(['code' => 'ZZX', 'is_active' => false]);

        $this->actingAs($manager)
            ->post(route('system.currencies.enable', $currency))
            ->assertForbidden();

        $this->assertFalse($currency->refresh()->is_active);
    }
}
