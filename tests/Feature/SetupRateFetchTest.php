<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupRateFetchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fetch_returns_inverted_spread_applied_rates_per_code(): void
    {
        // Provider quotes CCY-per-MYR; the wizard needs MYR-per-CCY.
        Http::fake([
            '*' => Http::response([
                'rates' => [
                    'IDR' => 3850,
                    'BND' => 0.29,
                ],
            ]),
        ]);

        $response = $this->postJson('/setup/rates/fetch', [
            'codes' => ['idr', 'BND', 'ZZZ'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $rates = $response->json('rates');

        // 1 / 3850 = 0.00025974 MYR per IDR
        $this->assertEqualsWithDelta(0.00025974, (float) $rates['IDR']['mid'], 0.000001);
        $this->assertLessThan((float) $rates['IDR']['mid'], (float) $rates['IDR']['buy']);
        $this->assertGreaterThan((float) $rates['IDR']['mid'], (float) $rates['IDR']['sell']);

        // 1 / 0.29 = 3.44827586 MYR per BND
        $this->assertEqualsWithDelta(3.44827586, (float) $rates['BND']['mid'], 0.000001);

        // Unknown codes surface as missing rather than zero-filled.
        $this->assertSame(['ZZZ'], $response->json('missing'));
    }

    #[Test]
    public function fetch_rejects_empty_code_list(): void
    {
        $this->postJson('/setup/rates/fetch', ['codes' => []])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function fetch_reports_failure_when_provider_is_down(): void
    {
        Http::fake(['*' => Http::response('upstream error', 503)]);

        $this->postJson('/setup/rates/fetch', ['codes' => ['IDR']])
            ->assertStatus(502)
            ->assertJson(['success' => false]);
    }
}
