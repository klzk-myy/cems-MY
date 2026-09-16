<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateStalenessCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function createRateWithUpdatedAt(string $updatedAt): ExchangeRate
    {
        /** @var ExchangeRate $rate */
        $rate = ExchangeRate::factory()->create([
            'currency_code' => 'USD',
            'fetched_at' => now(),
        ]);

        $rate->forceFill(['updated_at' => $updatedAt])->saveQuietly();

        return $rate;
    }

    #[Test]
    public function raises_warning_alert_when_rates_are_stale(): void
    {
        $this->createRateWithUpdatedAt(now()->subHours(24));

        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $alert = SystemAlert::query()
            ->where('source', 'rate_staleness')
            ->where('level', 'warning')
            ->first();

        $this->assertNotNull($alert);
        $this->assertStringContainsString('stale', $alert->message);
    }

    #[Test]
    public function does_not_alert_when_rates_are_fresh(): void
    {
        $this->createRateWithUpdatedAt(now()->subHour());

        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $this->assertSame(0, SystemAlert::query()->where('source', 'rate_staleness')->count());
    }

    #[Test]
    public function does_not_duplicate_an_open_staleness_alert(): void
    {
        $this->createRateWithUpdatedAt(now()->subHours(24));

        $this->artisanCommand('rates:staleness-check')->assertSuccessful();
        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $this->assertSame(1, SystemAlert::query()->where('source', 'rate_staleness')->count());
    }

    #[Test]
    public function succeeds_when_no_rates_exist(): void
    {
        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $this->assertSame(0, SystemAlert::query()->where('source', 'rate_staleness')->count());
    }

    #[Test]
    public function alerts_when_an_active_currency_has_no_rate_card(): void
    {
        Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'JPY']);
        $this->createRateWithUpdatedAt(now()->subHour());

        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $alert = SystemAlert::query()->where('source', 'rate_missing')->first();

        $this->assertNotNull($alert);
        $this->assertStringContainsString('JPY', $alert->message);
        $this->assertContains('JPY', $alert->metadata['currencies'] ?? []);
        $this->assertNotContains('USD', $alert->metadata['currencies'] ?? []);
    }

    #[Test]
    public function does_not_duplicate_an_open_missing_card_alert(): void
    {
        Currency::factory()->create(['code' => 'JPY']);
        $this->createRateWithUpdatedAt(now()->subHour());

        $this->artisanCommand('rates:staleness-check')->assertSuccessful();
        $this->artisanCommand('rates:staleness-check')->assertSuccessful();

        $this->assertSame(1, SystemAlert::query()->where('source', 'rate_missing')->count());
    }
}
