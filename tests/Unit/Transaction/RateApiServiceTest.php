<?php

namespace Tests\Unit\Transaction;

use App\Exceptions\Domain\InvalidRateException;
use App\Models\Currency;
use App\Models\ExchangeRate;
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

class RateApiServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fetch_latest_rates_throws_exception_when_api_key_is_missing(): void
    {
        // Override the config to simulate missing API key
        config(['services.exchange_rate_api.key' => null]);

        $service = new RateApiService(new MathService, new CacheInvalidationService, new ThresholdService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXCHANGE_RATE_API_KEY is not configured. Set it in .env');

        $service->fetchLatestRates();
    }

    #[Test]
    public function fetch_latest_rates_inverts_ccy_per_myr_payload_before_storing(): void
    {
        config(['services.exchange_rate_api.key' => 'test-key']);

        Currency::factory()->create(['code' => 'USD']);
        $systemUser = User::factory()->create();
        config(['cems.system_user_id' => $systemUser->id]);

        // Provider quotes CCY-per-MYR: 1 MYR = 0.21 USD.
        Http::fake([
            '*' => Http::response([
                'rates' => ['USD' => 0.21],
                'time_last_updated' => 1700000000,
            ]),
        ]);

        (new RateApiService(new MathService, new CacheInvalidationService, new ThresholdService))->fetchLatestRates();

        // Stored values are MYR-per-USD: mid = 1/0.21 = 4.76190476, with the
        // default 2% spread applied (buy below mid, sell above).
        $rate = ExchangeRate::where('currency_code', 'USD')->firstOrFail();
        $this->assertEqualsWithDelta(4.66666667, (float) $rate->rate_buy, 0.0001);
        $this->assertEqualsWithDelta(4.85714286, (float) $rate->rate_sell, 0.0001);
    }

    #[Test]
    public function repeated_calls_during_upstream_outage_do_not_hammer_the_api(): void
    {
        config(['services.exchange_rate_api.key' => 'test-key']);

        Http::fake(['*' => Http::response('upstream down', 503)]);

        $service = new RateApiService(new MathService, new CacheInvalidationService, new ThresholdService);

        // retry(3, 100) escalates a 503 to RequestException — the exact
        // exception type is the pre-existing contract.
        try {
            $service->fetchLatestRates();
            $this->fail('expected fetch to throw');
        } catch (\Throwable) {
        }

        $callsAfterFirstFailure = Http::recorded()->count();
        $this->assertGreaterThan(0, $callsAfterFirstFailure);

        // Second call within the fail-fast window must not hit upstream at all.
        try {
            $service->fetchLatestRates();
            $this->fail('expected InvalidRateException');
        } catch (InvalidRateException $e) {
            $this->assertStringContainsString('fail-fast', $e->getMessage());
        }

        $this->assertSame($callsAfterFirstFailure, Http::recorded()->count());
    }

    #[Test]
    public function fetch_recovers_after_fail_fast_window_expires(): void
    {
        config(['services.exchange_rate_api.key' => 'test-key']);

        Currency::factory()->create(['code' => 'USD']);
        config(['cems.system_user_id' => User::factory()->create()->id]);

        // The retry ladder consumes three 503s; the next call (after the
        // marker expires) gets the recovered response.
        Http::fakeSequence()
            ->push('upstream down', 503)
            ->push('upstream down', 503)
            ->push('upstream down', 503)
            ->push(['rates' => ['USD' => 0.21], 'time_last_updated' => 1700000000]);

        $service = new RateApiService(new MathService, new CacheInvalidationService, new ThresholdService);

        try {
            $service->fetchLatestRates();
            $this->fail('expected fetch to throw');
        } catch (\Throwable) {
        }

        // Expire the marker, then upstream recovers.
        Cache::forget(
            CacheKeys::exchangeRates(null).':failure'
        );

        $rates = $service->fetchLatestRates();

        $this->assertArrayHasKey('USD', $rates);
    }
}
