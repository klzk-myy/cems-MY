<?php

namespace Tests\Unit;

use App\Models\CurrencyPosition;
use App\Services\Accounting\CurrencyPositionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyPositionServiceCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected int $branchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchId = $this->createTestBranch()->id;
    }

    #[Test]
    public function get_available_balance_does_not_use_cache()
    {
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->branchId,
            'balance' => '1000.00',
        ]);

        Cache::shouldReceive('remember')
            ->never();

        $service = app(CurrencyPositionService::class);
        $balance = $service->getAvailableBalance('USD', (string) $this->branchId);

        $this->assertSame('1000.000000', $balance);
    }

    #[Test]
    public function update_position_invalidates_cache()
    {
        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->branchId,
            'balance' => '1000.00',
        ]);

        Cache::shouldReceive('forget')
            ->once()
            ->with("position:{$this->branchId}:USD:available");

        $service = app(CurrencyPositionService::class);
        $service->updatePosition('USD', '500.00', '1.25', 'Buy', (string) $this->branchId);
    }
}
