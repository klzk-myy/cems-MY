<?php

namespace Tests\Unit\Transaction;

use App\Services\Transaction\RateApiService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateApiServiceTest extends TestCase
{
    #[Test]
    public function fetch_latest_rates_throws_exception_when_api_key_is_missing(): void
    {
        // Override the config to simulate missing API key
        config(['services.exchange_rate_api.key' => null]);

        $service = new RateApiService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXCHANGE_RATE_API_KEY is not configured. Set it in .env');

        $service->fetchLatestRates();
    }
}
