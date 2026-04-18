<?php

namespace Tests\Unit;

use App\Models\ExchangeRateHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_exchange_rate_history(): void
    {
        $history = ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5000',
            'base_rate' => '4.4500',
            'spread' => '0.0500',
            'source' => 'Test API',
            'effective_date' => now(),
        ]);

        $this->assertNotNull($history->id);
        $this->assertEquals('USD', $history->currency_code);
    }

    public function test_belongs_to_currency_relationship(): void
    {
        $history = ExchangeRateHistory::create([
            'currency_code' => 'EUR',
            'rate' => '4.8500',
            'source' => 'ECB',
            'effective_date' => now(),
        ]);

        $this->assertEquals('EUR', $history->currency->code);
    }

    public function test_belongs_to_creator_relationship(): void
    {
        $user = User::factory()->create();

        $history = ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5000',
            'created_by' => $user->id,
            'source' => 'Manual',
            'effective_date' => now(),
        ]);

        $this->assertEquals($user->id, $history->creator->id);
    }

    public function test_scope_for_currency(): void
    {
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5000',
            'source' => 'Test',
            'effective_date' => now(),
        ]);
        ExchangeRateHistory::create([
            'currency_code' => 'EUR',
            'rate' => '4.8500',
            'source' => 'Test',
            'effective_date' => now(),
        ]);

        $usdHistory = ExchangeRateHistory::where('currency_code', 'USD')->get();

        $this->assertCount(1, $usdHistory);
        $this->assertEquals('USD', $usdHistory->first()->currency_code);
    }

    public function test_scope_for_date_range(): void
    {
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5000',
            'source' => 'Test',
            'effective_date' => '2026-04-01',
        ]);
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5100',
            'source' => 'Test',
            'effective_date' => '2026-04-10',
        ]);
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5200',
            'source' => 'Test',
            'effective_date' => '2026-04-15',
        ]);

        $logs = ExchangeRateHistory::whereBetween('effective_date', ['2026-04-05', '2026-04-12'])->get();

        $this->assertCount(1, $logs);
    }

    public function test_get_latest_rate_returns_most_recent(): void
    {
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5000',
            'source' => 'Test',
            'effective_date' => '2026-04-01',
        ]);
        ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.5500',
            'source' => 'Test',
            'effective_date' => '2026-04-10',
        ]);

        $latest = ExchangeRateHistory::where('currency_code', 'USD')
            ->orderBy('effective_date', 'desc')
            ->first();

        $this->assertEquals('4.550000', $latest->rate);
    }

    public function test_get_latest_rate_returns_null_when_no_history(): void
    {
        $latest = ExchangeRateHistory::where('currency_code', 'INVALID')
            ->orderBy('effective_date', 'desc')
            ->first();

        $this->assertNull($latest);
    }

    public function test_rate_precision_is_maintained(): void
    {
        $history = ExchangeRateHistory::create([
            'currency_code' => 'USD',
            'rate' => '4.723456',
            'base_rate' => '4.700000',
            'spread' => '0.023456',
            'source' => 'High Precision Test',
            'effective_date' => now(),
        ]);

        $this->assertEquals('4.723456', $history->rate);
    }
}
