<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\System\CacheInvalidationService;
use App\Services\System\CacheKeys;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\RateApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The rate-card write path (RateApiService::storeRatesToTable).
 *
 * Guards the regression where an upsert() keyed on (currency_code, branch_id)
 * could never match a company-wide card: databases treat NULLs as distinct in
 * a unique index, so every API fetch inserted a duplicate card row instead of
 * updating the existing one.
 */
class ExchangeRateCardPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.exchange_rate_api.key' => 'test-key']);
        config(['cems.system_user_id' => User::factory()->create()->id]);

        // The provider quotes CCY-per-MYR: 1 MYR = 0.21 USD.
        Http::fake([
            '*' => Http::response([
                'rates' => ['USD' => 0.21],
                'time_last_updated' => 1700000000,
            ]),
        ]);
    }

    protected function service(): RateApiService
    {
        return new RateApiService(new MathService, new CacheInvalidationService, new ThresholdService);
    }

    /**
     * The processed payload is cached for the configured TTL, so a second
     * fetch in the same test would short-circuit before touching the database.
     */
    protected function forgetFetchCache(?int $branchId = null): void
    {
        Cache::forget(CacheKeys::exchangeRates($branchId));
    }

    #[Test]
    public function repeated_company_wide_fetches_update_one_card_instead_of_duplicating_it(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $this->service()->fetchLatestRates();
        $this->forgetFetchCache();
        $this->service()->fetchLatestRates();

        $cards = ExchangeRate::where('currency_code', 'USD')->whereNull('branch_id')->get();

        $this->assertCount(1, $cards, 'A company-wide fetch must update the existing card, not insert a duplicate.');
    }

    #[Test]
    public function a_company_wide_fetch_never_writes_a_branch_card(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $this->service()->fetchLatestRates();

        $this->assertSame(0, ExchangeRate::where('currency_code', 'USD')->whereNotNull('branch_id')->count());
    }

    #[Test]
    public function repeated_branch_fetches_update_one_branch_card(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        $branch = Branch::factory()->create();

        $this->service()->fetchLatestRates($branch->id);
        $this->forgetFetchCache($branch->id);
        $this->service()->fetchLatestRates($branch->id);

        $branchCards = ExchangeRate::where('currency_code', 'USD')->where('branch_id', $branch->id)->get();

        $this->assertCount(1, $branchCards);
        $this->assertSame(0, ExchangeRate::where('currency_code', 'USD')->whereNull('branch_id')->count());
    }

    #[Test]
    public function fetched_cards_and_history_rows_record_the_spread_applied(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $this->service()->fetchLatestRates();

        $spread = (string) app(ThresholdService::class)->get('rates', 'spread', 0.02);
        $this->assertTrue(is_numeric($spread));
        $expected = bcadd(bcmul($spread, '100', 4), '0', 4);

        $card = ExchangeRate::where('currency_code', 'USD')->whereNull('branch_id')->firstOrFail();
        $this->assertSame($expected, (string) $card->spread_applied);

        $history = ExchangeRateHistory::where('currency_code', 'USD')
            ->whereNull('branch_id')
            ->whereDate('effective_date', today())
            ->firstOrFail();
        $this->assertSame($expected, (string) $history->spread_applied);
    }

    #[Test]
    public function a_scheduled_future_dated_card_is_not_overwritten_by_a_fetch(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $card = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.9000',
            'rate_sell' => '5.0000',
            'source' => 'manual_override',
            'fetched_at' => now(),
            'effective_date' => now()->addDay(),
        ]);

        $this->service()->fetchLatestRates();

        $card->refresh();

        $this->assertSame('4.90000000', (string) $card->rate_buy);
        $this->assertTrue($card->effective_date->isFuture());
        $this->assertSame('manual_override', $card->source);
        $this->assertSame(1, ExchangeRate::where('currency_code', 'USD')->count());
    }

    #[Test]
    public function an_elapsed_scheduled_card_is_refreshed_immediately(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $card = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'rate_buy' => '4.9000',
            'rate_sell' => '5.0000',
            'source' => 'manual_override',
            'fetched_at' => now(),
            'effective_date' => now()->subDay(),
        ]);

        $this->service()->fetchLatestRates();

        $card->refresh();

        $this->assertNull($card->effective_date);
        $this->assertSame('api', $card->source);
    }
}
