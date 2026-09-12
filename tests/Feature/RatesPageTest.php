<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
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
        $this->assertEquals('4.459000', $buy);
        $this->assertEquals('4.641000', $sell);
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
            '4.500000',
            (string) ExchangeRate::where('currency_code', 'USD')->value('rate_buy')
        );
    }
}
