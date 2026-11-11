<?php

namespace Tests\Feature;

use App\Models\Bi\AnalyticsDailyCurrency;
use App\Models\Bi\AnalyticsDailyForecast;
use App\Models\Branch;
use App\Models\User;
use App\Services\Bi\DemandForecaster;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 5: currency demand forecasting over the rollups (seasonal
 * blend = 0.5 * same-weekday mean of last 4 occurrences + 0.5 *
 * trailing-14-day mean). Oracle fixtures use last-4-Mondays = 700 so
 * Monday predicts 400.0000 and the following Tuesday 50.0000.
 */
class BiForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function monday(): Carbon
    {
        return Carbon::now()->next(Carbon::MONDAY);
    }

    /**
     * Last 4 Mondays each demand 700 units of $qtyColumn; every other day
     * is absent (zero demand).
     */
    protected function seedMondayHistory(Branch $branch, Carbon $monday, string $qty = '700.0000', string $volume = '1400.0000'): void
    {
        foreach ([1, 2, 3, 4] as $weeksAgo) {
            AnalyticsDailyCurrency::create([
                'date' => $monday->copy()->subWeeks($weeksAgo)->toDateString(),
                'branch_id' => $branch->id,
                'currency_code' => 'USD',
                'buy_qty' => $qty,
                'sell_qty' => '0',
                'buy_volume_myr' => $volume,
                'sell_volume_myr' => '0',
                'realized_margin_myr' => '0',
            ]);
        }
    }

    #[Test]
    public function forecast_blends_seasonal_and_trailing_means(): void
    {
        $branch = Branch::factory()->create();
        $monday = $this->monday();
        $this->seedMondayHistory($branch, $monday);

        app(DemandForecaster::class)->generate($monday, 7);

        $mondayRow = AnalyticsDailyForecast::query()
            ->where('branch_id', $branch->id)
            ->where('currency_code', 'USD')
            ->where('date', $monday->toDateString())
            ->firstOrFail();

        // 0.5 * 700 (last-4-Monday mean) + 0.5 * (1400/14 = 100 trailing)
        $this->assertSame('400.0000', $mondayRow->predicted_buy_qty);
        $this->assertSame('800.0000', $mondayRow->predicted_buy_volume_myr);
        $this->assertSame('0.0000', $mondayRow->predicted_sell_qty);
        $this->assertSame(4, $mondayRow->sample_days);

        $tuesdayRow = AnalyticsDailyForecast::query()
            ->where('branch_id', $branch->id)
            ->where('currency_code', 'USD')
            ->where('date', $monday->copy()->addDay()->toDateString())
            ->firstOrFail();

        // 0.5 * 0 (no Tuesday history) + 0.5 * 100
        $this->assertSame('50.0000', $tuesdayRow->predicted_buy_qty);
    }

    #[Test]
    public function consolidated_branch_null_row_sums_branch_predictions(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $monday = $this->monday();
        $this->seedMondayHistory($branchA, $monday, '700.0000');
        $this->seedMondayHistory($branchB, $monday, '140.0000');

        app(DemandForecaster::class)->generate($monday, 1);

        $consolidated = AnalyticsDailyForecast::query()
            ->whereNull('branch_id')
            ->where('currency_code', 'USD')
            ->where('date', $monday->toDateString())
            ->firstOrFail();

        // Branch A: 0.5*700 + 0.5*100 = 400; Branch B: 0.5*140 + 0.5*20 = 80
        $this->assertSame('480.0000', $consolidated->predicted_buy_qty);
    }

    #[Test]
    public function generate_is_idempotent_across_reruns(): void
    {
        $branch = Branch::factory()->create();
        $monday = $this->monday();
        $this->seedMondayHistory($branch, $monday);

        $forecaster = app(DemandForecaster::class);
        $first = $forecaster->generate($monday, 7);
        $second = $forecaster->generate($monday, 7);

        $this->assertSame($first, $second);
        // 7 days × (1 branch row + 1 consolidated row)
        $this->assertSame(14, AnalyticsDailyForecast::count());
    }

    #[Test]
    public function forecast_command_generates_rows_from_tomorrow(): void
    {
        $branch = Branch::factory()->create();
        $this->seedMondayHistory($branch, $this->monday());

        $this->artisanCommand('bi:forecast')->assertSuccessful();

        $this->assertGreaterThan(
            0,
            AnalyticsDailyForecast::where('date', Carbon::tomorrow()->toDateString())->count()
        );
    }

    #[Test]
    public function forecast_page_requires_permission_and_shows_predictions(): void
    {
        $this->get(route('bi.forecast'))->assertRedirect();
        $this->actingAs(User::factory()->teller()->create())
            ->get(route('bi.forecast'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        // Seed history ending yesterday so tomorrow's forecast exists.
        $tomorrow = Carbon::tomorrow();
        $this->seedMondayHistory($branch, Carbon::now()->next(Carbon::MONDAY));
        app(DemandForecaster::class)->generate($tomorrow, 7);

        $this->actingAs($admin)
            ->get(route('bi.forecast'))
            ->assertOk()
            ->assertSee('Currency Demand Forecast')
            ->assertSee('7-Day Forecast')
            ->assertSee('USD');
    }

    #[Test]
    public function forecast_page_scopes_predictions_for_branch_viewers(): void
    {
        $manager = User::factory()->manager()->create();
        $other = Branch::factory()->create();
        $monday = $this->monday();

        $this->seedMondayHistory($manager->branch, $monday, '700.0000');
        $this->seedMondayHistory($other, $monday, '140.0000');
        app(DemandForecaster::class)->generate(Carbon::tomorrow(), 7);

        $response = $this->actingAs($manager)->get(route('bi.forecast', ['currency' => 'USD']))->assertOk();
        $response->assertSee('Demand Trend');

        // Manager's forecast rows are pinned to their own branch — the
        // consolidated 480 and the other branch's 80 never reach the view.
        /** @var Collection<int, AnalyticsDailyForecast> $forecasts */
        $forecasts = $response->viewData('forecasts');
        $this->assertGreaterThan(0, $forecasts->count());
        foreach ($forecasts as $row) {
            $this->assertSame($manager->branch_id, $row->branch_id);
        }
    }

    #[Test]
    public function empty_history_produces_zero_predictions_not_errors(): void
    {
        $branch = Branch::factory()->create();
        $monday = $this->monday();

        // A single present-day row with zero demand.
        AnalyticsDailyCurrency::create([
            'date' => $monday->copy()->subDays(3)->toDateString(),
            'branch_id' => $branch->id,
            'currency_code' => 'EUR',
            'buy_qty' => '0',
            'sell_qty' => '0',
            'buy_volume_myr' => '0',
            'sell_volume_myr' => '0',
            'realized_margin_myr' => '0',
        ]);

        app(DemandForecaster::class)->generate($monday, 7);

        $row = AnalyticsDailyForecast::where('branch_id', $branch->id)->where('currency_code', 'EUR')
            ->where('date', $monday->toDateString())->firstOrFail();
        $this->assertSame('0.0000', $row->predicted_buy_qty);
    }
}
