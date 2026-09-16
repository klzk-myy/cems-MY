<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            // Head-office view: no branch scoping so seeded rates are visible.
            'branch_id' => null,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function manager_sees_currency_rate_rows(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now()->subMinutes(10),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('USD');
        $response->assertSee(number_format(4.5, 4));
        $response->assertSee(number_format(4.6, 4));
        $response->assertSee('Override');
    }

    #[Test]
    public function page_shows_empty_state_when_no_rates_exist(): void
    {
        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('No exchange rates configured yet.');
    }

    #[Test]
    public function stale_rates_show_warning_badge(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'EUR',
            'fetched_at' => now()->subHours(config('cems.rate_staleness_hours') + 2),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('Stale');
    }

    #[Test]
    public function admin_sees_branch_selector(): void
    {
        Branch::factory()->create(['name' => 'Kuala Lumpur HQ', 'is_active' => true]);

        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('name="branch_id"', false);
        $response->assertSee('Kuala Lumpur HQ');
    }

    #[Test]
    public function copy_previous_day_updates_current_rates(): void
    {
        $branch = Branch::factory()->create();

        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '9.9999',
            'rate_sell' => '9.9999',
            'branch_id' => null,
            'fetched_at' => now(),
        ]);

        ExchangeRateHistory::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => null,
            'rate' => '4.550000',
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)
            ->post(route('rates.copy-previous'), [
                'date' => now()->subDay()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // History stores the mid rate; the copy must re-apply the configured
        // spread (default 2%): buy = mid * 0.98, sell = mid * 1.02.
        $rate = ExchangeRate::where('currency_code', 'USD')->first();
        $buy = (string) $rate->getAttribute('rate_buy');
        $sell = (string) $rate->getAttribute('rate_sell');
        $this->assertEquals('4.45900000', $buy);
        $this->assertEquals('4.64100000', $sell);
        $this->assertTrue(
            app(MathService::class)->compare($sell, $buy) > 0,
            'Copied rates must keep a positive spread (sell > buy)'
        );
    }

    #[Test]
    public function override_with_excessive_spread_is_rejected_via_web_form(): void
    {
        ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.5000',
            'rate_sell' => '4.6000',
            'fetched_at' => now(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        // Spread = 6.98% > max 5%
        $response = $this->actingAs($manager)
            ->post(route('rates.override'), [
                'currency_code' => 'USD',
                'rate_buy' => '4.0000',
                'rate_sell' => '4.6000',
                'reason' => 'Testing spread guard',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(
            '4.50000000',
            (string) ExchangeRate::where('currency_code', 'USD')->value('rate_buy')
        );
    }

    #[Test]
    public function units_page_lists_active_currencies_with_unit_quoted_card(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '235.0000',
            'rate_sell' => '245.0000',
            'rate_unit' => 1000000,
            'fetched_at' => now(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.units'));

        $response->assertOk();
        $response->assertSee('IDR');
        $response->assertSee('value="1000000"', false);
        $response->assertSee('value="235.00000000"', false);
        $response->assertSee('value="245.00000000"', false);
    }

    #[Test]
    public function units_page_requotes_card_stored_in_a_different_unit(): void
    {
        // Card stored per 1,000 while the currency unit is 1,000,000 —
        // 0.235 per 1,000 → 235 per 1,000,000.
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '0.2350',
            'rate_sell' => '0.2450',
            'rate_unit' => 1000,
            'fetched_at' => now(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.units'));

        $response->assertOk();
        $response->assertSee('value="235.00000000"', false);
        $response->assertSee('value="245.00000000"', false);
    }

    #[Test]
    public function update_units_saves_quote_unit_and_writes_unit_quoted_card(): void
    {
        Currency::factory()->create(['code' => 'IDR']);

        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '1000000',
            'rate_inverse' => '0',
            'rate_buy' => '235',
            'rate_sell' => '245',
            'reason' => 'Set IDR quote unit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('currencies', [
            'code' => 'IDR',
            'rate_unit' => 1000000,
        ]);

        $rate = ExchangeRate::where('currency_code', 'IDR')->firstOrFail();
        $this->assertSame('235.00000000', (string) $rate->rate_buy);
        $this->assertSame('245.00000000', (string) $rate->rate_sell);
        $this->assertSame(1000000, (int) $rate->rate_unit);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'rate_unit_changed',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function update_units_only_changes_unit_when_no_rates_submitted(): void
    {
        Currency::factory()->create(['code' => 'IDR']);

        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '100000',
            'rate_inverse' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(100000, (int) Currency::find('IDR')->rate_unit);
        $this->assertDatabaseMissing('exchange_rates', ['currency_code' => 'IDR']);
    }

    #[Test]
    public function index_displays_rates_in_the_configured_quote_unit(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '235.0000',
            'rate_sell' => '245.0000',
            'rate_unit' => 1000000,
            'fetched_at' => now(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('per 1,000,000');
        $response->assertSee('235.0000');
        $response->assertSee('245.0000');
    }

    #[Test]
    public function update_units_saves_inverse_direction_and_inverse_quoted_card(): void
    {
        Currency::factory()->create(['code' => 'IDR']);

        $admin = $this->createUser(UserRole::Admin);

        // Inverse: RM 1 = 4,400 IDR buy / 4,200 IDR sell — buy is the
        // larger number for inverse quotes.
        $response = $this->actingAs($admin)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '1',
            'rate_inverse' => '1',
            'rate_buy' => '4400',
            'rate_sell' => '4200',
            'reason' => 'Switch IDR to inverse quote',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $currency = Currency::find('IDR');
        $this->assertTrue((bool) $currency->rate_inverse);

        $rate = ExchangeRate::where('currency_code', 'IDR')->firstOrFail();
        $this->assertSame('4400.00000000', (string) $rate->rate_buy);
        $this->assertSame('4200.00000000', (string) $rate->rate_sell);
        $this->assertTrue((bool) $rate->rate_inverse);
        $this->assertSame('0.00022727', $rate->perUnitRate((string) $rate->rate_buy));
        $this->assertSame('0.00023809', $rate->perUnitRate((string) $rate->rate_sell));
    }

    #[Test]
    public function update_units_rejects_inverse_card_where_sell_exceeds_buy(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_inverse' => true]);

        $manager = $this->createUser(UserRole::Manager);

        // Inverse semantics: sell > buy in quoted terms means the shop's
        // per-unit sell rate is below its buy rate — invalid.
        $response = $this->actingAs($manager)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '1',
            'rate_inverse' => '1',
            'rate_buy' => '4200',
            'rate_sell' => '4400',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('exchange_rates', ['currency_code' => 'IDR']);
    }

    #[Test]
    public function update_units_rolls_back_unit_change_when_card_save_fails(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $admin = $this->createUser(UserRole::Admin);

        // sell < buy in direct terms fails the sell > buy invariant — the
        // unit change must not persist on its own.
        $response = $this->actingAs($admin)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '100000',
            'rate_inverse' => '0',
            'rate_buy' => '240',
            'rate_sell' => '235',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1000000, (int) Currency::find('IDR')->rate_unit);
        $this->assertDatabaseMissing('exchange_rates', ['currency_code' => 'IDR']);
        $this->assertDatabaseMissing('system_logs', [
            'action' => 'rate_unit_changed',
            'entity_type' => 'Currency',
        ]);
    }

    #[Test]
    public function update_units_denies_convention_change_for_non_admin(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $manager = $this->createUser(UserRole::Manager);

        // rate_unit lives on the global currencies row — a manager's change
        // would affect every branch.
        $response = $this->actingAs($manager)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '100000',
            'rate_inverse' => '0',
            'rate_buy' => '23.5',
            'rate_sell' => '24.5',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1000000, (int) Currency::find('IDR')->rate_unit);
        $this->assertDatabaseMissing('exchange_rates', ['currency_code' => 'IDR']);
    }

    #[Test]
    public function update_units_allows_manager_card_save_without_convention_change(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1000000]);

        $manager = $this->createUser(UserRole::Manager);

        // Same convention as the currency — only the card is written.
        $response = $this->actingAs($manager)->post(route('rates.units.update'), [
            'currency_code' => 'IDR',
            'rate_unit' => '1000000',
            'rate_inverse' => '0',
            'rate_buy' => '235',
            'rate_sell' => '245',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('235.00000000', (string) ExchangeRate::where('currency_code', 'IDR')->firstOrFail()->rate_buy);
    }

    #[Test]
    public function index_labels_inverse_rates_per_rm(): void
    {
        Currency::factory()->create(['code' => 'IDR', 'rate_unit' => 1, 'rate_inverse' => true]);

        ExchangeRate::factory()->create([
            'currency_code' => 'IDR',
            'rate_buy' => '4400.0000',
            'rate_sell' => '4200.0000',
            'rate_unit' => 1,
            'rate_inverse' => true,
            'fetched_at' => now(),
        ]);

        $manager = $this->createUser(UserRole::Manager);

        $response = $this->actingAs($manager)->get(route('rates.index'));

        $response->assertOk();
        $response->assertSee('per RM 1');
        $response->assertSee('4,400.0000');
        $response->assertSee('4,200.0000');
    }

    #[Test]
    public function units_page_rejects_users_without_access_rates_permission(): void
    {
        $teller = $this->createUser(UserRole::Teller);

        $this->actingAs($teller)->get(route('rates.units'))->assertForbidden();
    }
}
