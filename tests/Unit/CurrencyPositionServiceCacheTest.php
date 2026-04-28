<?php

namespace Tests\Unit;

use App\Models\CurrencyPosition;
use App\Services\CurrencyPositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CurrencyPositionServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_available_balance_uses_cache()
    {
        // Create a position
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 1,
            'balance' => '1000.00',
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('1000.00');

        $service = app(CurrencyPositionService::class);
        // getAvailableBalance expects tillId as string? Our method takes string $tillId, but we'll pass '1' (string) or 1 will be cast
        $balance = $service->getAvailableBalance('USD', 1);

        $this->assertEquals('1000.00', $balance);
    }

    public function test_update_position_invalidates_cache()
    {
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'till_id' => 1,
            'balance' => '1000.00',
        ]);

        Cache::shouldReceive('forget')
            ->once()
            ->with('position:1:USD:available');

        $service = app(CurrencyPositionService::class);
        // Call updatePosition with required parameters: amount, rate, type, tillId
        $service->updatePosition('USD', '500.00', '1.25', 'Buy', 1);
    }
}
